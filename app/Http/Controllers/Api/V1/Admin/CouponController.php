<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendCouponRequest;
use App\Http\Requests\Admin\StoreCouponRequest;
use App\Http\Requests\Admin\UpdateCouponRequest;
use App\Http\Resources\Coupon\CouponResource;
use App\Jobs\SendWhatsAppMessageJob;
use App\Mail\CouponMarketingMail;
use App\Models\Coupon;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;

class CouponController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->integer('per_page', 20), 100));

        $query = Coupon::query()
            ->with(['users:id,name,email,username', 'triggerCoupon:id,code,name'])
            ->withCount(['users', 'redemptions'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));

                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->has('is_active') && $request->input('is_active') !== '', function ($query) use ($request) {
                $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

                if ($isActive !== null) {
                    $query->where('is_active', $isActive);
                }
            })
            ->when($request->has('is_general') && $request->input('is_general') !== '', function ($query) use ($request) {
                $isGeneral = filter_var($request->input('is_general'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

                if ($isGeneral !== null) {
                    $query->where('is_general', $isGeneral);
                }
            })
            ->latest('id');

        $coupons = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'ok' => true,
            'data' => CouponResource::collection($coupons->getCollection()),
            'meta' => [
                'current_page' => $coupons->currentPage(),
                'last_page' => $coupons->lastPage(),
                'per_page' => $coupons->perPage(),
                'total' => $coupons->total(),
                'from' => $coupons->firstItem(),
                'to' => $coupons->lastItem(),
            ],
        ]);
    }

    public function formOptions(): JsonResponse
    {
        $clients = User::query()
            ->with('customerProfile:id,user_id,whatsapp')
            ->where('role_id', User::ROLE_CLIENTE)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'username'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'whatsapp' => $user->customerProfile?->whatsapp,
            ])
            ->values();

        $coupons = Coupon::query()
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (Coupon $coupon) => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'name' => $coupon->name,
            ])
            ->values();

        return response()->json([
            'ok' => true,
            'data' => [
                'discount_types' => [
                    ['value' => Coupon::DISCOUNT_TYPE_FIXED, 'label' => 'Monto fijo'],
                    ['value' => Coupon::DISCOUNT_TYPE_PERCENTAGE, 'label' => 'Porcentaje'],
                ],
                'clients' => $clients,
                'coupons' => $coupons,
                'channels' => [
                    ['value' => 'email', 'label' => 'Correo'],
                    ['value' => 'whatsapp', 'label' => 'WhatsApp'],
                ],
            ],
        ]);
    }

    public function store(StoreCouponRequest $request): JsonResponse
    {
        $data = $request->validated();
        $userIds = $data['user_ids'] ?? [];
        $campaign = $data['campaign'] ?? null;
        $metadata = $data['metadata'] ?? [];

        if ($campaign) {
            data_set($metadata, 'campaign', $campaign);
            $data['metadata'] = $metadata;
        }

        unset($data['user_ids'], $data['campaign']);

        $coupon = Coupon::create($data);

        if (!$coupon->is_general) {
            $coupon->users()->sync($userIds);
        }

        $this->scheduleEmailCampaignIfRequested($coupon->fresh('users'), $campaign);

        return response()->json([
            'ok' => true,
            'message' => 'Cupón creado correctamente.',
            'data' => new CouponResource($coupon->fresh()->load(['users', 'triggerCoupon'])),
        ], 201);
    }

    public function show(Coupon $coupon): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => new CouponResource($coupon->load(['users', 'triggerCoupon'])),
        ]);
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon): JsonResponse
    {
        $data = $request->validated();
        $userIds = $data['user_ids'] ?? [];
        $campaign = $data['campaign'] ?? null;
        $metadata = $data['metadata'] ?? ($coupon->metadata ?? []);

        if ($campaign) {
            data_set($metadata, 'campaign', $campaign);
            $data['metadata'] = $metadata;
        }

        unset($data['user_ids'], $data['campaign']);

        $coupon->update($data);

        if ($coupon->is_general) {
            $coupon->users()->sync([]);
        } elseif ($request->has('user_ids')) {
            $coupon->users()->sync($userIds);
        }

        $this->scheduleEmailCampaignIfRequested($coupon->fresh('users'), $campaign);

        return response()->json([
            'ok' => true,
            'message' => 'Cupón actualizado correctamente.',
            'data' => new CouponResource($coupon->fresh()->load(['users', 'triggerCoupon'])),
        ]);
    }

    public function assignUsers(Request $request, Coupon $coupon): JsonResponse
    {
        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ], [
            'user_ids.required' => 'Debes seleccionar al menos un usuario.',
            'user_ids.array' => 'Los usuarios asignados deben enviarse como una lista.',
            'user_ids.min' => 'Debes seleccionar al menos un usuario.',
            'user_ids.*.exists' => 'Uno de los usuarios seleccionados no existe.',
        ]);

        $userIds = collect($data['user_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $clientIds = User::query()
            ->whereIn('id', $userIds)
            ->where('role_id', User::ROLE_CLIENTE)
            ->pluck('id');

        abort_if($userIds->diff($clientIds)->isNotEmpty(), 422, 'Solo puedes asignar clientes al cupón.');

        $coupon->forceFill(['is_general' => false])->save();
        $coupon->users()->sync($userIds);

        return response()->json([
            'ok' => true,
            'message' => 'Usuarios asignados al cupón correctamente.',
            'data' => new CouponResource($coupon->fresh()->load(['users', 'triggerCoupon'])),
        ]);
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        $coupon->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Cupón eliminado correctamente.',
        ]);
    }

    public function toggle(Coupon $coupon): JsonResponse
    {
        $coupon->update([
            'is_active' => ! $coupon->is_active,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Estado del cupón actualizado correctamente.',
            'data' => new CouponResource($coupon->fresh()->load(['users', 'triggerCoupon'])),
        ]);
    }

    public function send(SendCouponRequest $request, Coupon $coupon): JsonResponse
    {
        $data = $request->validated();
        $channels = collect($data['channels'])->unique()->values();
        $users = User::query()
            ->with('customerProfile:id,user_id,whatsapp')
            ->whereIn('id', $data['user_ids'] ?? [])
            ->get(['id', 'name', 'email']);

        $emails = collect($data['emails'] ?? [])
            ->merge($users->pluck('email'))
            ->filter()
            ->unique()
            ->values();

        $whatsappNumbers = collect($data['whatsapp_numbers'] ?? [])
            ->merge($users->map(fn (User $user) => $user->customerProfile?->whatsapp))
            ->filter()
            ->unique()
            ->values();

        $sent = [
            'email' => 0,
            'whatsapp' => 0,
        ];

        if ($channels->contains('email')) {
            foreach ($emails as $email) {
                Mail::to($email)->queue(new CouponMarketingMail(
                    coupon: $coupon,
                    customMessage: $data['message'] ?? null,
                    customSubject: $data['subject'] ?? null
                ));
                $sent['email']++;
            }
        }

        if ($channels->contains('whatsapp')) {
            foreach ($whatsappNumbers as $number) {
                SendWhatsAppMessageJob::dispatch($number, $this->whatsappMessage($coupon, $data['message'] ?? null));
                $sent['whatsapp']++;
            }
        }

        return response()->json([
            'ok' => true,
            'message' => 'Envío de cupón programado correctamente.',
            'data' => [
                'coupon_id' => $coupon->id,
                'channels' => $channels,
                'sent' => $sent,
            ],
        ]);
    }

    protected function whatsappMessage(Coupon $coupon, ?string $customMessage = null): string
    {
        $discount = $coupon->discount_type === Coupon::DISCOUNT_TYPE_PERCENTAGE
            ? number_format((float) $coupon->discount_value, 2) . '%'
            : '$' . number_format((float) $coupon->discount_value, 2);

        $message = $customMessage ?: 'Tenemos un cupón para tu próxima compra.';
        $expires = $coupon->ends_at ? "\nVálido hasta: {$coupon->ends_at->toDateTimeString()}" : '';

        return "{$message}\nCupón: {$coupon->code}\nDescuento: {$discount}{$expires}";
    }

    protected function scheduleEmailCampaignIfRequested(Coupon $coupon, ?array $campaign): void
    {
        if (! filter_var(data_get($campaign, 'send_email', false), FILTER_VALIDATE_BOOL)) {
            return;
        }

        $users = User::query()
            ->where('role_id', User::ROLE_CLIENTE)
            ->when(data_get($campaign, 'user_ids'), fn ($query, $ids) => $query->whereIn('id', $ids))
            ->whereNotNull('email')
            ->get(['id', 'email']);

        $sendAt = data_get($campaign, 'send_at') ? Carbon::parse(data_get($campaign, 'send_at')) : null;

        foreach ($users as $user) {
            $mail = Mail::to($user->email);

            if ($sendAt && $sendAt->isFuture()) {
                $mail->later($sendAt, new CouponMarketingMail(
                    coupon: $coupon,
                    customMessage: data_get($campaign, 'message'),
                    customSubject: data_get($campaign, 'subject')
                ));

                continue;
            }

            $mail->queue(new CouponMarketingMail(
                coupon: $coupon,
                customMessage: data_get($campaign, 'message'),
                customSubject: data_get($campaign, 'subject')
            ));
        }
    }
}
