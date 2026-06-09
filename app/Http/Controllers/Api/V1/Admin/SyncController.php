<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminProductResource;
use App\Models\Category;
use App\Models\ClaveArticulo;
use App\Models\ClaveCliente;
use App\Models\CustomerProfile;
use App\Models\DoctoVe;
use App\Models\DoctoVeDetalle;
use App\Models\Family;
use App\Models\Impuesto;
use App\Models\ImpuestoArticulo;
use App\Models\PrecioArticulo;
use App\Models\PrecioCliCli;
use App\Models\PrecioEmpresa;
use App\Models\Product;
use App\Models\TipoImpuesto;
use App\Models\User;
use App\Models\UserAddress;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SyncController extends Controller
{
    private const DEFAULT_DOCTO_VE_COND_PAGO_ID = 556;

    private const CATEGORY_FIELD_MAP = [
        'GRUPO_LINEA_ID' => 'grupo_linea_id',
        'NOMBRE' => 'name',
        'CUENTA_ALMACEN' => 'cuenta_almacen',
        'CUENTA_COSTO_VENTA' => 'cuenta_costo_venta',
        'CUENTA_VENTAS' => 'cuenta_ventas',
        'CUENTA_DSCTO_VENTAS' => 'cuenta_dscto_ventas',
        'CUENTA_DEVOL_VENTAS' => 'cuenta_devol_ventas',
        'CUENTA_COMPRAS' => 'cuenta_compras',
        'CUENTA_DEVOL_COMPRAS' => 'cuenta_devol_compras',
        'APLICAR_FACTOR_VENTA' => 'aplicar_factor_venta',
        'FACTOR_VENTA' => 'factor_venta',
        'ES_PREDET' => 'es_predet',
        'OCULTO' => 'oculto',
        'USUARIO_CREADOR' => 'usuario_creador',
        'FECHA_HORA_CREACION' => 'fecha_hora_creacion',
        'USUARIO_AUT_CREACION' => 'usuario_aut_creacion',
        'USUARIO_ULT_MODIF' => 'usuario_ult_modif',
        'FECHA_HORA_ULT_MODIF' => 'fecha_hora_ult_modif',
        'USUARIO_AUT_MODIF' => 'usuario_aut_modif',
    ];

    private const FAMILY_FIELD_MAP = [
        'LINEA_ARTICULO_ID' => 'linea_articulo_id',
        'NOMBRE' => 'name',
        'GRUPO_LINEA_ID' => 'grupo_linea_id',
        'CUENTA_ALMACEN' => 'cuenta_almacen',
        'CUENTA_COSTO_VENTA' => 'cuenta_costo_venta',
        'CUENTA_VENTAS' => 'cuenta_ventas',
        'CUENTA_DSCTO_VENTAS' => 'cuenta_dscto_ventas',
        'CUENTA_DEVOL_VENTAS' => 'cuenta_devol_ventas',
        'CUENTA_COMPRAS' => 'cuenta_compras',
        'CUENTA_DEVOL_COMPRAS' => 'cuenta_devol_compras',
        'APLICAR_FACTOR_VENTA' => 'aplicar_factor_venta',
        'FACTOR_VENTA' => 'factor_venta',
        'ES_PREDET' => 'es_predet',
        'OCULTO' => 'oculto',
        'USUARIO_CREADOR' => 'usuario_creador',
        'FECHA_HORA_CREACION' => 'fecha_hora_creacion',
        'USUARIO_AUT_CREACION' => 'usuario_aut_creacion',
        'USUARIO_ULT_MODIF' => 'usuario_ult_modif',
        'FECHA_HORA_ULT_MODIF' => 'fecha_hora_ult_modif',
        'USUARIO_AUT_MODIF' => 'usuario_aut_modif',
    ];

    private const PRODUCT_FIELD_MAP = [
        'ARTICULO_ID' => 'microsip_id',
        'NOMBRE' => 'name',
        'ES_ALMACENABLE' => 'es_almacenable',
        'ES_JUEGO' => 'es_juego',
        'ESTATUS' => 'estatus',
        'CAUSA_SUSP' => 'causa_susp',
        'FECHA_SUSP' => 'fecha_susp',
        'IMPRIMIR_COMP' => 'imprimir_comp',
        'PERMITIR_AGREGAR_COMP' => 'permitir_agregar_comp',
        'LINEA_ARTICULO_ID' => 'linea_articulo_id',
        'UNIDAD_VENTA' => 'unidad_venta',
        'UNIDAD_COMPRA' => 'unidad_compra',
        'CONTENIDO_UNIDAD_COMPRA' => 'contenido_unidad_compra',
        'PESO_UNITARIO' => 'peso_unitario',
        'ES_PESO_VARIABLE' => 'es_peso_variable',
        'SEGUIMIENTO' => 'seguimiento',
        'DIAS_GARANTIA' => 'dias_garantia',
        'ES_IMPORTADO' => 'es_importado',
        'ES_SIEMPRE_IMPORTADO' => 'es_siempre_importado',
        'PCTJE_ARANCEL' => 'pctje_arancel',
        'NOTAS_COMPRAS' => 'notas_compras',
        'IMPRIMIR_NOTAS_COMPRAS' => 'imprimir_notas_compras',
        'NOTAS_VENTAS' => 'notas_ventas',
        'IMPRIMIR_NOTAS_VENTAS' => 'imprimir_notas_ventas',
        'ES_PRECIO_VARIABLE' => 'es_precio_variable',
        'CUENTA_ALMACEN' => 'cuenta_almacen',
        'CUENTA_COSTO_VENTA' => 'cuenta_costo_venta',
        'CUENTA_VENTAS' => 'cuenta_ventas',
        'CUENTA_DSCTO_VENTAS' => 'cuenta_dscto_ventas',
        'CUENTA_DEVOL_VENTAS' => 'cuenta_devol_ventas',
        'CUENTA_COMPRAS' => 'cuenta_compras',
        'CUENTA_DEVOL_COMPRAS' => 'cuenta_devol_compras',
        'APLICAR_FACTOR_VENTA' => 'aplicar_factor_venta',
        'FACTOR_VENTA' => 'factor_venta',
        'RED_PRECIO_CON_IMPTO' => 'red_precio_con_impto',
        'FACTOR_RED_PRECIO_CON_IMPTO' => 'factor_red_precio_con_impto',
        'USUARIO_CREADOR' => 'usuario_creador',
        'FECHA_HORA_CREACION' => 'fecha_hora_creacion',
        'USUARIO_AUT_CREACION' => 'usuario_aut_creacion',
        'USUARIO_ULT_MODIF' => 'usuario_ult_modif',
        'FECHA_HORA_ULT_MODIF' => 'fecha_hora_ult_modif',
        'USUARIO_AUT_MODIF' => 'usuario_aut_modif',
    ];

    private const CUSTOMER_FIELD_MAP = [
        'CLIENTE_ID' => 'microsip_id',
        'CLAVE_CLIENTE' => 'clave_cliente',
        'NOMBRE' => 'name',
        'CONTACTO1' => 'contacto1',
        'CONTACTO2' => 'contacto2',
        'ESTATUS' => 'estatus',
        'CAUSA_SUSP' => 'causa_susp',
        'FECHA_SUSP' => 'fecha_susp',
        'COBRAR_IMPUESTOS' => 'cobrar_impuestos',
        'RETIENE_IMPUESTOS' => 'retiene_impuestos',
        'SUJETO_IEPS' => 'sujeto_ieps',
        'GENERAR_INTERESES' => 'generar_intereses',
        'EMITIR_EDOCTA' => 'emitir_edocta',
        'DIFERIR_CFDI_COBROS' => 'diferir_cfdi_cobros',
        'LIMITE_CREDITO' => 'limite_credito',
        'MONEDA_ID' => 'moneda_id',
        'COND_PAGO_ID' => 'cond_pago_id',
        'TIPO_CLIENTE_ID' => 'tipo_cliente_id',
        'ZONA_CLIENTE_ID' => 'zona_cliente_id',
        'COBRADOR_ID' => 'cobrador_id',
        'VENDEDOR_ID' => 'vendedor_id',
        'NOTAS' => 'notas',
        'CUENTA_CXC' => 'cuenta_cxc',
        'CUENTA_ANTICIPOS' => 'cuenta_anticipos',
        'FORMATOS_EMAIL' => 'formatos_email',
        'RECEPTOR_CFD' => 'receptor_cfd',
        'NUM_PROV_CLIENTE' => 'num_prov_cliente',
        'CAMPOS_ADDENDA' => 'campos_addenda',
        'USUARIO_CREADOR' => 'usuario_creador',
        'FECHA_HORA_CREACION' => 'fecha_hora_creacion',
        'USUARIO_AUT_CREACION' => 'usuario_aut_creacion',
        'USUARIO_ULT_MODIF' => 'usuario_ult_modif',
        'FECHA_HORA_ULT_MODIF' => 'fecha_hora_ult_modif',
        'USUARIO_AUT_MODIF' => 'usuario_aut_modif',
        'CFDIW_USUARIO' => 'cfdiw_usuario',
        'CFDIW_PASSWORD' => 'cfdiw_password',
        'CFDIW_ESTATUS' => 'cfdiw_estatus',
        'CDFIW_FORMATO_CFD_VE' => 'cdfiw_formato_cfd_ve',
        'CDFIW_FORMATO_CFDI_VE' => 'cdfiw_formato_cfdi_ve',
        'CDFIW_FORMATO_DEV_CFD_VE' => 'cdfiw_formato_dev_cfd_ve',
        'CDFIW_FORMATO_DEV_CFDI_VE' => 'cdfiw_formato_dev_cfdi_ve',
        'CDFIW_FORMATO_CFD_PV' => 'cdfiw_formato_cfd_pv',
        'CDFIW_FORMATO_CFDI_PV' => 'cdfiw_formato_cfdi_pv',
        'CDFIW_FORMATO_DEV_CFD_PV' => 'cdfiw_formato_dev_cfd_pv',
        'CDFIW_FORMATO_DEV_CFDI_PV' => 'cdfiw_formato_dev_cfdi_pv',
    ];

    private const DIR_CLIENTE_FIELD_MAP = [
        'DIR_CLI_ID' => 'dir_cli_id',
        'CLIENTE_ID' => 'cliente_id',
        'NOMBRE_CONSIG' => 'nombre_consig',
        'CALLE' => 'calle',
        'NOMBRE_CALLE' => 'nombre_calle',
        'NUM_EXTERIOR' => 'num_exterior',
        'NUM_INTERIOR' => 'num_interior',
        'COLONIA' => 'colonia',
        'COLONIA_CLAVE_FISCAL' => 'colonia_clave_fiscal',
        'POBLACION' => 'poblacion',
        'POBLACION_CLAVE_FISC' => 'poblacion_clave_fisc',
        'REFERENCIA' => 'referencia',
        'CIUDAD_ID' => 'ciudad_id',
        'ESTADO_ID' => 'estado_id',
        'CODIGO_POSTAL' => 'codigo_postal',
        'PAIS_ID' => 'pais_id',
        'TELEFONO1' => 'telefono1',
        'TELEFONO2' => 'telefono2',
        'FAX' => 'fax',
        'EMAIL' => 'email',
        'RFC_CURP' => 'rfc_curp',
        'TIPO_PERSONA' => 'tipo_persona',
        'CLAVE_REGIMEN_FISCAL' => 'clave_regimen_fiscal',
        'TAX_ID' => 'tax_id',
        'CONTACTO' => 'contacto',
        'VIA_EMBARQUE_ID' => 'via_embarque_id',
        'ES_DIR_PPAL' => 'es_dir_ppal',
        'USAR_PARA_ENVIOS' => 'usar_para_envios',
        'USAR_PARA_FACTURAR' => 'usar_para_facturar',
        'GLN' => 'gln',
    ];

    private const CLAVE_ARTICULO_FIELD_MAP = [
        'CLAVE_ARTICULO_ID' => 'clave_articulo_id',
        'CLAVE_ARTICULO' => 'clave_articulo',
        'ARTICULO_ID' => 'articulo_id',
        'ROL_CLAVE_ART_ID' => 'rol_clave_art_id',
        'CONTENIDO_EMPAQUE' => 'contenido_empaque',
    ];

    private const CLAVE_CLIENTE_FIELD_MAP = [
        'CLAVE_CLIENTE_ID' => 'clave_cliente_id',
        'CLAVE_CLIENTE' => 'clave_cliente',
        'CLIENTE_ID' => 'cliente_id',
        'ROL_CLAVE_CLI_ID' => 'rol_clave_cli_id',
    ];

    private const PRECIO_ARTICULO_FIELD_MAP = [
        'PRECIO_ARTICULO_ID' => 'precio_articulo_id',
        'ARTICULO_ID' => 'articulo_id',
        'PRECIO_EMPRESA_ID' => 'precio_empresa_id',
        'PRECIO' => 'precio',
        'MONEDA_ID' => 'moneda_id',
        'MARGEN' => 'margen',
        'MARKUP' => 'markup',
        'FECHA_HORA_ULT_MODIF' => 'fecha_hora_ult_modif',
    ];

    private const PRECIO_EMPRESA_FIELD_MAP = [
        'PRECIO_EMPRESA_ID' => 'precio_empresa_id',
        'NOMBRE' => 'nombre',
        'ID_INTERNO' => 'id_interno',
        'ACT_AUTOMATICA' => 'act_automatica',
        'PRECIO_EMPRESA_ACT_AUTC' => 'precio_empresa_act_autc',
        'PORCENTAJE' => 'porcentaje',
        'USAR_TABLA_FACTORES' => 'usar_tabla_factores',
        'FACTOR_REDONDEO' => 'factor_redondeo',
        'AGREGAR_PRECIOS' => 'agregar_precios',
        'POSICION' => 'posicion',
        'USUARIO_CREADOR' => 'usuario_creador',
        'FECHA_HORA_CREACION' => 'fecha_hora_creacion',
        'USUARIO_AUT_CREACION' => 'usuario_aut_creacion',
        'USUARIO_ULT_MODIF' => 'usuario_ult_modif',
        'FECHA_HORA_ULT_MODIF' => 'fecha_hora_ult_modif',
        'USUARIO_AUT_MODIF' => 'usuario_aut_modif',
    ];

    private const PRECIO_CLI_CLI_FIELD_MAP = [
        'PRECIO_CLI_CLI_ID' => 'precio_cli_cli_id',
        'POLITICA_PRECIOS_CLI_ID' => 'politica_precios_cli_id',
        'CLAVE_CLIENTE' => 'clave_cliente',
        'CLIENTE_ID' => 'cliente_id',
        'PRECIO_EMPRESA_ID' => 'precio_empresa_id',
        'POLITICA_DSCTO_ART_CLI_' => 'politica_dscto_art_cli_id',
        'POLITICA_DSCTO_ART_CLI_ID' => 'politica_dscto_art_cli_id',
    ];

    private const TIPO_IMPUESTO_FIELD_MAP = [
        'TIPO_IMPTO_ID' => 'tipo_impto_id',
        'NOMBRE' => 'nombre',
        'TIPO' => 'tipo',
        'GRAVA_OTROS_IMPTOS' => 'grava_otros_imptos',
        'APLICA_SOLO_SOBRE_IMPTE_IMP' => 'aplica_solo_sobre_impte_imp',
        'ID_INTERNO' => 'id_interno',
        'ES_PREDET' => 'es_predet',
        'USUARIO_CREADOR' => 'usuario_creador',
        'FECHA_HORA_CREACION' => 'fecha_hora_creacion',
        'USUARIO_AUT_CREACION' => 'usuario_aut_creacion',
        'USUARIO_ULT_MODIF' => 'usuario_ult_modif',
        'FECHA_HORA_ULT_MODIF' => 'fecha_hora_ult_modif',
        'USUARIO_AUT_MODIF' => 'usuario_aut_modif',
    ];

    private const IMPUESTO_FIELD_MAP = [
        'IMPUESTO_ID' => 'impuesto_id',
        'TIPO_IMPTO_ID' => 'tipo_impto_id',
        'NOMBRE' => 'nombre',
        'TIPO_CALC' => 'tipo_calc',
        'PCTJE_IMPUESTO' => 'pctje_impuesto',
        'IMPORTE_UNITARIO' => 'importe_unitario',
        'UNIDAD_IMPTO' => 'unidad_impto',
        'ES_PREDET' => 'es_predet',
        'OCULTO' => 'oculto',
        'CAUSA_FLUJO_EFECTIVO' => 'causa_flujo_efectivo',
        'CUENTA_PEND_EN_VENTAS' => 'cuenta_pend_en_ventas',
        'CUENTA_EN_VENTAS' => 'cuenta_en_ventas',
        'CUENTA_PEND_EN_COMPRAS' => 'cuenta_pend_en_compras',
        'CUENTA_EN_COMPRAS' => 'cuenta_en_compras',
        'TIPO_IVA' => 'tipo_iva',
        'USUARIO_CREADOR' => 'usuario_creador',
        'FECHA_HORA_CREACION' => 'fecha_hora_creacion',
        'USUARIO_AUT_CREACION' => 'usuario_aut_creacion',
        'USUARIO_ULT_MODIF' => 'usuario_ult_modif',
        'FECHA_HORA_ULT_MODIF' => 'fecha_hora_ult_modif',
        'USUARIO_AUT_MODIF' => 'usuario_aut_modif',
    ];

    private const IMPUESTO_ARTICULO_FIELD_MAP = [
        'IMPUESTO_ART_ID' => 'impuesto_art_id',
        'ARTICULO_ID' => 'articulo_id',
        'IMPUESTO_ID' => 'impuesto_id',
        'UNIDADES_IMPUESTO' => 'unidades_impuesto',
        'TIPO_SELECCION' => 'tipo_seleccion',
        'CONJUNTO_SUCURSALES_ID' => 'conjunto_sucursales_id',
        'CONJUNTO_SUCURSALES_' => 'conjunto_sucursales_id',
    ];

    /**
     * Upsert categories from Microsip GRUPOS_LINEAS.
     */
    public function categories(Request $request): JsonResponse
    {
        $items = $this->payloadItems($request, 'grupos_lineas', ['categories']);
        $created = 0;
        $updated = 0;
        $errors = [];
        $categories = collect();

        foreach ($items as $index => $item) {
            $data = $this->normalizePayload($item, self::CATEGORY_FIELD_MAP);
            $validator = Validator::make($data, $this->categoryRules());

            if ($validator->fails()) {
                $errors[] = [
                    'index' => $index,
                    'grupo_linea_id' => $data['grupo_linea_id'] ?? null,
                    'errors' => $validator->errors()->toArray(),
                ];

                continue;
            }

            $validated = $validator->validated();
            $category = Category::query()
                ->where('grupo_linea_id', (int) $validated['grupo_linea_id'])
                ->first();

            $category = Category::updateOrCreate(
                ['grupo_linea_id' => (int) $validated['grupo_linea_id']],
                $this->categoryUpsertPayload($validated, $category)
            );

            $categories->push($category);
            $category->wasRecentlyCreated ? $created++ : $updated++;
        }

        return response()->json([
            'ok' => empty($errors),
            'message' => empty($errors)
                ? 'Categorías sincronizadas correctamente.'
                : 'Sincronización de categorías finalizada con errores.',
            'summary' => [
                'received' => count($items),
                'created' => $created,
                'updated' => $updated,
                'failed' => count($errors),
            ],
            'errors' => $errors,
            'data' => $categories,
        ], empty($errors) ? 200 : 422);
    }

    /**
     * Upsert families from Microsip LINEAS_ARTICULOS.
     */
    public function families(Request $request): JsonResponse
    {
        $items = $this->payloadItems($request, 'lineas_articulos', ['families']);
        $created = 0;
        $updated = 0;
        $errors = [];
        $families = collect();

        foreach ($items as $index => $item) {
            $data = $this->normalizePayload($item, self::FAMILY_FIELD_MAP);
            $validator = Validator::make($data, $this->familyRules());

            if ($validator->fails()) {
                $errors[] = [
                    'index' => $index,
                    'linea_articulo_id' => $data['linea_articulo_id'] ?? null,
                    'grupo_linea_id' => $data['grupo_linea_id'] ?? null,
                    'errors' => $validator->errors()->toArray(),
                ];

                continue;
            }

            $validated = $validator->validated();
            $category = Category::query()
                ->where('grupo_linea_id', (int) $validated['grupo_linea_id'])
                ->first();

            if (! $category) {
                $errors[] = [
                    'index' => $index,
                    'linea_articulo_id' => $validated['linea_articulo_id'],
                    'grupo_linea_id' => $validated['grupo_linea_id'],
                    'errors' => [
                        'grupo_linea_id' => ['No existe una categoría sincronizada con este GRUPO_LINEA_ID.'],
                    ],
                ];

                continue;
            }

            $family = Family::query()
                ->where('linea_articulo_id', (int) $validated['linea_articulo_id'])
                ->first();

            $family = Family::updateOrCreate(
                ['linea_articulo_id' => (int) $validated['linea_articulo_id']],
                $this->familyUpsertPayload($validated, $category, $family)
            );

            $family->load('category');
            $families->push($family);

            $family->wasRecentlyCreated ? $created++ : $updated++;
        }

        return response()->json([
            'ok' => empty($errors),
            'message' => empty($errors)
                ? 'Familias sincronizadas correctamente.'
                : 'Sincronización de familias finalizada con errores.',
            'summary' => [
                'received' => count($items),
                'created' => $created,
                'updated' => $updated,
                'failed' => count($errors),
            ],
            'errors' => $errors,
            'data' => $families,
        ], empty($errors) ? 200 : 422);
    }

    /**
     * Upsert products received from Microsip.
     */
    public function products(Request $request): JsonResponse
    {
        $items = $this->productPayloadItems($request);
        $defaultCategoryId = $request->integer('category_id') ?: $this->defaultMicrosipCategoryId();

        $created = 0;
        $updated = 0;
        $errors = [];
        $products = collect();

        foreach ($items as $index => $item) {
            $data = $this->normalizeProductPayload($item);

            $validator = Validator::make($data, $this->productRules());

            if ($validator->fails()) {
                $errors[] = [
                    'index' => $index,
                    'microsip_id' => $data['microsip_id'] ?? null,
                    'errors' => $validator->errors()->toArray(),
                ];

                continue;
            }

            $validated = $validator->validated();
            $product = Product::query()
                ->where('microsip_id', (string) $validated['microsip_id'])
                ->first();

            $payload = $this->productUpsertPayload($validated, $product, $defaultCategoryId);
            $product = Product::updateOrCreate(
                ['microsip_id' => (string) $validated['microsip_id']],
                $payload
            );

            $product->load(['category', 'family']);
            $products->push($product);

            $product->wasRecentlyCreated ? $created++ : $updated++;
        }

        return response()->json([
            'ok' => empty($errors),
            'message' => empty($errors)
                ? 'Productos sincronizados correctamente.'
                : 'Sincronización de productos finalizada con errores.',
            'summary' => [
                'received' => count($items),
                'created' => $created,
                'updated' => $updated,
                'failed' => count($errors),
            ],
            'errors' => $errors,
            'data' => AdminProductResource::collection($products),
        ], empty($errors) ? 200 : 422);
    }

    /**
     * Upsert customers received from Microsip.
     */
    public function customers(Request $request): JsonResponse
    {
        $items = $this->payloadItems($request, 'customers');
        $created = 0;
        $updated = 0;
        $errors = [];
        $customers = collect();

        DB::beginTransaction();

        try {
            foreach ($items as $index => $item) {
                $data = $this->normalizePayload($item, self::CUSTOMER_FIELD_MAP);

                $validator = Validator::make($data, $this->customerRules());

                if ($validator->fails()) {
                    $errors[] = [
                        'index' => $index,
                        'microsip_id' => $data['microsip_id'] ?? null,
                        'errors' => $validator->errors()->toArray(),
                    ];

                    continue;
                }

                $validated = $validator->validated();
                $user = User::query()
                    ->where('microsip_id', (string) $validated['microsip_id'])
                    ->first();

                $payload = $this->customerUpsertPayload($validated, $user);
                $user = User::updateOrCreate(
                    ['microsip_id' => (string) $validated['microsip_id']],
                    $payload
                );

                $this->syncCustomerProfile($user, $validated);
                $user->load(['role', 'customerProfile']);
                $customers->push($user);

                $user->wasRecentlyCreated ? $created++ : $updated++;
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'ok' => false,
                'message' => 'No fue posible sincronizar clientes.',
                'error' => $th->getMessage(),
            ], 500);
        }

        return response()->json([
            'ok' => empty($errors),
            'message' => empty($errors)
                ? 'Clientes sincronizados correctamente.'
                : 'Sincronización de clientes finalizada con errores.',
            'summary' => [
                'received' => count($items),
                'created' => $created,
                'updated' => $updated,
                'failed' => count($errors),
            ],
            'errors' => $errors,
            'data' => $customers,
        ], empty($errors) ? 200 : 422);
    }

    /**
     * Upsert customer addresses from Microsip DIRS_CLIENTES.
     */
    public function dirsClientes(Request $request): JsonResponse
    {
        $items = $this->payloadItems($request, 'dirs_clientes', ['customer_addresses', 'addresses']);
        $created = 0;
        $updated = 0;
        $errors = [];
        $addresses = collect();

        DB::beginTransaction();

        try {
            foreach ($items as $index => $item) {
                $data = $this->normalizePayload($item, self::DIR_CLIENTE_FIELD_MAP);
                $validator = Validator::make($data, $this->dirClienteRules());

                if ($validator->fails()) {
                    $errors[] = [
                        'index' => $index,
                        'dir_cli_id' => $data['dir_cli_id'] ?? null,
                        'cliente_id' => $data['cliente_id'] ?? null,
                        'errors' => $validator->errors()->toArray(),
                    ];

                    continue;
                }

                $validated = $validator->validated();
                $user = User::query()
                    ->where('microsip_id', (string) $validated['cliente_id'])
                    ->first();

                if (! $user) {
                    $errors[] = [
                        'index' => $index,
                        'dir_cli_id' => $validated['dir_cli_id'],
                        'cliente_id' => $validated['cliente_id'],
                        'errors' => [
                            'cliente_id' => ['No existe un cliente sincronizado con este CLIENTE_ID.'],
                        ],
                    ];

                    continue;
                }

                $address = UserAddress::query()
                    ->where('dir_cli_id', (int) $validated['dir_cli_id'])
                    ->first();

                $payload = $this->dirClienteUpsertPayload($validated, $user, $address);

                if ((bool) $payload['is_default']) {
                    $defaultQuery = UserAddress::query()->where('user_id', $user->id);

                    if ($address) {
                        $defaultQuery->whereKeyNot($address->id);
                    }

                    $defaultQuery->update([
                        'is_default' => false,
                        'es_dir_ppal' => 'N',
                    ]);
                }

                $address = UserAddress::updateOrCreate(
                    ['dir_cli_id' => (int) $validated['dir_cli_id']],
                    $payload
                );

                $addresses->push($address->fresh('user'));
                $address->wasRecentlyCreated ? $created++ : $updated++;
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'ok' => false,
                'message' => 'No fue posible sincronizar direcciones de clientes.',
                'error' => $th->getMessage(),
            ], 500);
        }

        return response()->json([
            'ok' => empty($errors),
            'message' => empty($errors)
                ? 'Direcciones de clientes sincronizadas correctamente.'
                : 'Sincronización de direcciones de clientes finalizada con errores.',
            'summary' => [
                'received' => count($items),
                'created' => $created,
                'updated' => $updated,
                'failed' => count($errors),
            ],
            'errors' => $errors,
            'data' => $addresses,
        ], empty($errors) ? 200 : 422);
    }

    /**
     * Upsert Microsip article keys and relate them to local products.
     */
    public function clavesArticulos(Request $request): JsonResponse
    {
        $items = $this->payloadItems($request, 'claves_articulos');
        $created = 0;
        $updated = 0;
        $errors = [];
        $clavesArticulos = collect();

        foreach ($items as $index => $item) {
            $data = $this->normalizePayload($item, self::CLAVE_ARTICULO_FIELD_MAP);
            $validator = Validator::make($data, $this->claveArticuloRules());

            if ($validator->fails()) {
                $errors[] = [
                    'index' => $index,
                    'clave_articulo_id' => $data['clave_articulo_id'] ?? null,
                    'articulo_id' => $data['articulo_id'] ?? null,
                    'errors' => $validator->errors()->toArray(),
                ];

                continue;
            }

            $validated = $validator->validated();
            $product = Product::query()
                ->where('microsip_id', (string) $validated['articulo_id'])
                ->first();

            if (! $product) {
                $errors[] = [
                    'index' => $index,
                    'clave_articulo_id' => $validated['clave_articulo_id'],
                    'articulo_id' => $validated['articulo_id'],
                    'errors' => [
                        'articulo_id' => ['No existe un producto sincronizado con este ARTICULO_ID.'],
                    ],
                ];

                continue;
            }

            $payload = $this->claveArticuloUpsertPayload($validated, $product);
            $claveArticulo = ClaveArticulo::updateOrCreate(
                ['clave_articulo_id' => (string) $validated['clave_articulo_id']],
                $payload
            );

            $claveArticulo->load('product');
            $clavesArticulos->push($claveArticulo);

            $claveArticulo->wasRecentlyCreated ? $created++ : $updated++;
        }

        return response()->json([
            'ok' => empty($errors),
            'message' => empty($errors)
                ? 'Claves de artículos sincronizadas correctamente.'
                : 'Sincronización de claves de artículos finalizada con errores.',
            'summary' => [
                'received' => count($items),
                'created' => $created,
                'updated' => $updated,
                'failed' => count($errors),
            ],
            'errors' => $errors,
            'data' => $clavesArticulos,
        ], empty($errors) ? 200 : 422);
    }

    /**
     * Upsert Microsip customer keys without forcing local relationships.
     */
    public function clavesClientes(Request $request): JsonResponse
    {
        $items = $this->payloadItems($request, 'claves_clientes');
        $created = 0;
        $updated = 0;
        $errors = [];
        $clavesClientes = collect();

        foreach ($items as $index => $item) {
            $data = $this->normalizePayload($item, self::CLAVE_CLIENTE_FIELD_MAP);
            $validator = Validator::make($data, $this->claveClienteRules());

            if ($validator->fails()) {
                $errors[] = [
                    'index' => $index,
                    'clave_cliente_id' => $data['clave_cliente_id'] ?? null,
                    'cliente_id' => $data['cliente_id'] ?? null,
                    'errors' => $validator->errors()->toArray(),
                ];

                continue;
            }

            $validated = $validator->validated();
            $claveCliente = ClaveCliente::updateOrCreate(
                ['clave_cliente_id' => (int) $validated['clave_cliente_id']],
                $this->claveClienteUpsertPayload($validated)
            );

            $clavesClientes->push($claveCliente);
            $claveCliente->wasRecentlyCreated ? $created++ : $updated++;
        }

        return response()->json([
            'ok' => empty($errors),
            'message' => empty($errors)
                ? 'Claves de clientes sincronizadas correctamente.'
                : 'Sincronización de claves de clientes finalizada con errores.',
            'summary' => [
                'received' => count($items),
                'created' => $created,
                'updated' => $updated,
                'failed' => count($errors),
            ],
            'errors' => $errors,
            'data' => $clavesClientes,
        ], empty($errors) ? 200 : 422);
    }

    /**
     * Upsert Microsip article prices and relate them to local products.
     */
    public function preciosArticulos(Request $request): JsonResponse
    {
        $items = $this->payloadItems($request, 'precios_articulos');
        $created = 0;
        $updated = 0;
        $errors = [];
        $preciosArticulos = collect();

        foreach ($items as $index => $item) {
            $data = $this->normalizePayload($item, self::PRECIO_ARTICULO_FIELD_MAP);
            $validator = Validator::make($data, $this->precioArticuloRules());

            if ($validator->fails()) {
                $errors[] = [
                    'index' => $index,
                    'precio_articulo_id' => $data['precio_articulo_id'] ?? null,
                    'articulo_id' => $data['articulo_id'] ?? null,
                    'errors' => $validator->errors()->toArray(),
                ];

                continue;
            }

            $validated = $validator->validated();
            $product = Product::query()
                ->where('microsip_id', (string) $validated['articulo_id'])
                ->first();

            if (! $product) {
                $errors[] = [
                    'index' => $index,
                    'precio_articulo_id' => $validated['precio_articulo_id'],
                    'articulo_id' => $validated['articulo_id'],
                    'errors' => [
                        'articulo_id' => ['No existe un producto sincronizado con este ARTICULO_ID.'],
                    ],
                ];

                continue;
            }

            $payload = $this->precioArticuloUpsertPayload($validated, $product);
            $precioArticulo = PrecioArticulo::updateOrCreate(
                ['precio_articulo_id' => (int) $validated['precio_articulo_id']],
                $payload
            );

            $precioArticulo->load('product');
            $preciosArticulos->push($precioArticulo);

            $precioArticulo->wasRecentlyCreated ? $created++ : $updated++;
        }

        return response()->json([
            'ok' => empty($errors),
            'message' => empty($errors)
                ? 'Precios de artículos sincronizados correctamente.'
                : 'Sincronización de precios de artículos finalizada con errores.',
            'summary' => [
                'received' => count($items),
                'created' => $created,
                'updated' => $updated,
                'failed' => count($errors),
            ],
            'errors' => $errors,
            'data' => $preciosArticulos,
        ], empty($errors) ? 200 : 422);
    }

    /**
     * Upsert Microsip price companies.
     */
    public function preciosEmpresas(Request $request): JsonResponse
    {
        $items = $this->payloadItems($request, 'precios_empresa', ['precios_empresas']);
        $created = 0;
        $updated = 0;
        $errors = [];
        $preciosEmpresas = collect();

        foreach ($items as $index => $item) {
            $data = $this->normalizePayload($item, self::PRECIO_EMPRESA_FIELD_MAP);
            $validator = Validator::make($data, $this->precioEmpresaRules());

            if ($validator->fails()) {
                $errors[] = [
                    'index' => $index,
                    'precio_empresa_id' => $data['precio_empresa_id'] ?? null,
                    'errors' => $validator->errors()->toArray(),
                ];

                continue;
            }

            $validated = $validator->validated();
            $precioEmpresa = PrecioEmpresa::updateOrCreate(
                ['precio_empresa_id' => (int) $validated['precio_empresa_id']],
                $this->precioEmpresaUpsertPayload($validated)
            );

            $preciosEmpresas->push($precioEmpresa);
            $precioEmpresa->wasRecentlyCreated ? $created++ : $updated++;
        }

        return response()->json([
            'ok' => empty($errors),
            'message' => empty($errors)
                ? 'Precios empresa sincronizados correctamente.'
                : 'Sincronización de precios empresa finalizada con errores.',
            'summary' => [
                'received' => count($items),
                'created' => $created,
                'updated' => $updated,
                'failed' => count($errors),
            ],
            'errors' => $errors,
            'data' => $preciosEmpresas,
        ], empty($errors) ? 200 : 422);
    }

    /**
     * Upsert Microsip PRECIOS_CLI_CLI rows.
     */
    public function preciosCliCli(Request $request): JsonResponse
    {
        $items = $this->payloadItems($request, 'precios_cli_cli');
        $created = 0;
        $updated = 0;
        $errors = [];
        $preciosCliCli = collect();

        foreach ($items as $index => $item) {
            $data = $this->normalizePayload($item, self::PRECIO_CLI_CLI_FIELD_MAP);
            $validator = Validator::make($data, $this->precioCliCliRules());

            if ($validator->fails()) {
                $errors[] = [
                    'index' => $index,
                    'precio_cli_cli_id' => $data['precio_cli_cli_id'] ?? null,
                    'cliente_id' => $data['cliente_id'] ?? null,
                    'errors' => $validator->errors()->toArray(),
                ];

                continue;
            }

            $validated = $validator->validated();
            $precioCliCli = PrecioCliCli::updateOrCreate(
                ['precio_cli_cli_id' => (int) $validated['precio_cli_cli_id']],
                $this->precioCliCliUpsertPayload($validated)
            );

            $preciosCliCli->push($precioCliCli);
            $precioCliCli->wasRecentlyCreated ? $created++ : $updated++;
        }

        return response()->json([
            'ok' => empty($errors),
            'message' => empty($errors)
                ? 'Precios cliente-cliente sincronizados correctamente.'
                : 'Sincronización de precios cliente-cliente finalizada con errores.',
            'summary' => [
                'received' => count($items),
                'created' => $created,
                'updated' => $updated,
                'failed' => count($errors),
            ],
            'errors' => $errors,
            'data' => $preciosCliCli,
        ], empty($errors) ? 200 : 422);
    }

    /**
     * Upsert Microsip tax types.
     */
    public function tiposImpuestos(Request $request): JsonResponse
    {
        $items = $this->payloadItems($request, 'tipos_impuestos');
        $created = 0;
        $updated = 0;
        $errors = [];
        $tiposImpuestos = collect();

        foreach ($items as $index => $item) {
            $data = $this->normalizePayload($item, self::TIPO_IMPUESTO_FIELD_MAP);
            $validator = Validator::make($data, $this->tipoImpuestoRules());

            if ($validator->fails()) {
                $errors[] = [
                    'index' => $index,
                    'tipo_impto_id' => $data['tipo_impto_id'] ?? null,
                    'errors' => $validator->errors()->toArray(),
                ];

                continue;
            }

            $validated = $validator->validated();
            $tipoImpuesto = TipoImpuesto::updateOrCreate(
                ['tipo_impto_id' => (int) $validated['tipo_impto_id']],
                $this->tipoImpuestoUpsertPayload($validated)
            );

            $tiposImpuestos->push($tipoImpuesto);
            $tipoImpuesto->wasRecentlyCreated ? $created++ : $updated++;
        }

        return response()->json([
            'ok' => empty($errors),
            'message' => empty($errors)
                ? 'Tipos de impuestos sincronizados correctamente.'
                : 'Sincronización de tipos de impuestos finalizada con errores.',
            'summary' => [
                'received' => count($items),
                'created' => $created,
                'updated' => $updated,
                'failed' => count($errors),
            ],
            'errors' => $errors,
            'data' => $tiposImpuestos,
        ], empty($errors) ? 200 : 422);
    }

    /**
     * Upsert Microsip taxes.
     */
    public function impuestos(Request $request): JsonResponse
    {
        $items = $this->payloadItems($request, 'impuestos');
        $created = 0;
        $updated = 0;
        $errors = [];
        $impuestos = collect();

        foreach ($items as $index => $item) {
            $data = $this->normalizePayload($item, self::IMPUESTO_FIELD_MAP);
            $validator = Validator::make($data, $this->impuestoRules());

            if ($validator->fails()) {
                $errors[] = [
                    'index' => $index,
                    'impuesto_id' => $data['impuesto_id'] ?? null,
                    'errors' => $validator->errors()->toArray(),
                ];

                continue;
            }

            $validated = $validator->validated();
            $impuesto = Impuesto::updateOrCreate(
                ['impuesto_id' => (int) $validated['impuesto_id']],
                $this->impuestoUpsertPayload($validated)
            );

            $impuestos->push($impuesto);
            $impuesto->wasRecentlyCreated ? $created++ : $updated++;
        }

        return response()->json([
            'ok' => empty($errors),
            'message' => empty($errors)
                ? 'Impuestos sincronizados correctamente.'
                : 'Sincronización de impuestos finalizada con errores.',
            'summary' => [
                'received' => count($items),
                'created' => $created,
                'updated' => $updated,
                'failed' => count($errors),
            ],
            'errors' => $errors,
            'data' => $impuestos,
        ], empty($errors) ? 200 : 422);
    }

    /**
     * Upsert Microsip article taxes and relate them to local products.
     */
    public function impuestosArticulos(Request $request): JsonResponse
    {
        $items = $this->payloadItems($request, 'impuestos_articulos');
        $created = 0;
        $updated = 0;
        $errors = [];
        $impuestosArticulos = collect();

        foreach ($items as $index => $item) {
            $data = $this->normalizePayload($item, self::IMPUESTO_ARTICULO_FIELD_MAP);
            $validator = Validator::make($data, $this->impuestoArticuloRules());

            if ($validator->fails()) {
                $errors[] = [
                    'index' => $index,
                    'impuesto_art_id' => $data['impuesto_art_id'] ?? null,
                    'articulo_id' => $data['articulo_id'] ?? null,
                    'errors' => $validator->errors()->toArray(),
                ];

                continue;
            }

            $validated = $validator->validated();
            $product = Product::query()
                ->where('microsip_id', (string) $validated['articulo_id'])
                ->first();

            if (! $product) {
                $errors[] = [
                    'index' => $index,
                    'impuesto_art_id' => $validated['impuesto_art_id'],
                    'articulo_id' => $validated['articulo_id'],
                    'errors' => [
                        'articulo_id' => ['No existe un producto sincronizado con este ARTICULO_ID.'],
                    ],
                ];

                continue;
            }

            $impuestoArticulo = ImpuestoArticulo::updateOrCreate(
                ['impuesto_art_id' => (int) $validated['impuesto_art_id']],
                $this->impuestoArticuloUpsertPayload($validated, $product)
            );

            $impuestoArticulo->load('product');
            $impuestosArticulos->push($impuestoArticulo);

            $impuestoArticulo->wasRecentlyCreated ? $created++ : $updated++;
        }

        return response()->json([
            'ok' => empty($errors),
            'message' => empty($errors)
                ? 'Impuestos de artículos sincronizados correctamente.'
                : 'Sincronización de impuestos de artículos finalizada con errores.',
            'summary' => [
                'received' => count($items),
                'created' => $created,
                'updated' => $updated,
                'failed' => count($errors),
            ],
            'errors' => $errors,
            'data' => $impuestosArticulos,
        ], empty($errors) ? 200 : 422);
    }

    /**
     * List ecommerce sales documents ready for Microsip export.
     */
    public function doctosVe(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 50), 1), 200);

        $doctos = DoctoVe::query()
            ->with(['order.user', 'detalles.product'])
            ->where('sincronizado', $this->requestedSincronizado($request))
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'ok' => true,
            'data' => $doctos->getCollection()->map(fn (DoctoVe $docto) => $this->doctoVePayload($docto))->values(),
            'meta' => [
                'current_page' => $doctos->currentPage(),
                'last_page' => $doctos->lastPage(),
                'per_page' => $doctos->perPage(),
                'total' => $doctos->total(),
            ],
        ]);
    }

    /**
     * List only doctos_ve headers ready for Microsip export.
     */
    public function doctosVeEncabezados(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 50), 1), 200);

        $doctos = DoctoVe::query()
            ->where('sincronizado', $this->requestedSincronizado($request))
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'ok' => true,
            'table' => 'doctos_ve',
            'data' => $doctos->getCollection()->map(fn (DoctoVe $docto) => [
                'id' => $docto->id,
                'order_id' => $docto->order_id,
                'sync_status' => $docto->sync_status,
                'sincronizado' => (bool) $docto->sincronizado,
                'validation_errors' => $docto->validation_errors,
                'doctos_ve' => $this->doctoVeHeaderPayload($docto),
            ])->values(),
            'meta' => [
                'current_page' => $doctos->currentPage(),
                'last_page' => $doctos->lastPage(),
                'per_page' => $doctos->perPage(),
                'total' => $doctos->total(),
            ],
        ]);
    }

    /**
     * List only doctos_ve_detalles rows ready for Microsip export.
     */
    public function doctosVeDetalles(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 100), 1), 500);

        $detalles = DoctoVeDetalle::query()
            ->with('doctoVe')
            ->whereHas(
                'doctoVe',
                fn ($doctoQuery) => $doctoQuery->where('sincronizado', $this->requestedSincronizado($request))
            )
            ->when($request->filled('docto_ve_local_id'), fn ($query) => $query->where(
                'docto_ve_local_id',
                (int) $request->integer('docto_ve_local_id')
            ))
            ->when($request->filled('order_id'), fn ($query) => $query->whereHas(
                'doctoVe',
                fn ($doctoQuery) => $doctoQuery->where('order_id', (int) $request->integer('order_id'))
            ))
            ->orderBy('docto_ve_local_id')
            ->orderBy('posicion')
            ->paginate($perPage);

        return response()->json([
            'ok' => true,
            'table' => 'doctos_ve_detalles',
            'data' => $detalles->getCollection()->map(fn (DoctoVeDetalle $detalle) => [
                'id' => $detalle->id,
                'docto_ve_local_id' => $detalle->docto_ve_local_id,
                'order_id' => $detalle->doctoVe?->order_id,
                'sync_status' => $detalle->doctoVe?->sync_status,
                'sincronizado' => (bool) $detalle->doctoVe?->sincronizado,
                'doctos_ve_detalles' => $this->doctoVeDetallePayload($detalle),
            ])->values(),
            'meta' => [
                'current_page' => $detalles->currentPage(),
                'last_page' => $detalles->lastPage(),
                'per_page' => $detalles->perPage(),
                'total' => $detalles->total(),
            ],
        ]);
    }

    /**
     * Mark a local sales document as already synchronized with Microsip.
     */
    public function marcarDoctoVeSincronizado(Request $request, DoctoVe $doctoVe): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'docto_ve_id' => ['nullable', 'integer', 'min:1'],
            'detalles' => ['nullable', 'array'],
            'detalles.*.id' => ['nullable', 'integer', 'min:1'],
            'detalles.*.order_item_id' => ['nullable', 'integer', 'min:1'],
            'detalles.*.docto_ve_det_id' => ['nullable', 'integer', 'min:1'],
            'microsip_response' => ['nullable', 'array'],
        ])->validate();

        DB::transaction(function () use ($doctoVe, $validated) {
            $doctoVe = DoctoVe::query()
                ->with('detalles')
                ->whereKey($doctoVe->id)
                ->lockForUpdate()
                ->firstOrFail();

            $doctoVeId = $validated['docto_ve_id'] ?? $doctoVe->docto_ve_id;

            if ($doctoVeId) {
                $doctoVe->docto_ve_id = $doctoVeId;
            }

            $metadata = $doctoVe->metadata ?? [];
            if (isset($validated['microsip_response'])) {
                $metadata['microsip_response'] = $validated['microsip_response'];
            }

            $doctoVe->forceFill([
                'sync_status' => 'synced',
                'sincronizado' => true,
                'exported_at' => now(),
                'validation_errors' => null,
                'metadata' => $metadata,
            ])->save();

            if ($doctoVeId) {
                $doctoVe->detalles()->update(['docto_ve_id' => $doctoVeId]);
            }

            collect($validated['detalles'] ?? [])->each(function (array $detallePayload) use ($doctoVe, $doctoVeId) {
                if (! isset($detallePayload['docto_ve_det_id'])) {
                    return;
                }

                $query = $doctoVe->detalles();

                if (isset($detallePayload['id'])) {
                    $query->whereKey((int) $detallePayload['id']);
                } elseif (isset($detallePayload['order_item_id'])) {
                    $query->where('order_item_id', (int) $detallePayload['order_item_id']);
                } else {
                    return;
                }

                $query->update([
                    'docto_ve_id' => $doctoVeId,
                    'docto_ve_det_id' => (int) $detallePayload['docto_ve_det_id'],
                ]);
            });
        });

        $doctoVe->refresh()->load('detalles');

        return response()->json([
            'ok' => true,
            'message' => 'Venta marcada como sincronizada con Microsip.',
            'data' => $this->doctoVePayload($doctoVe),
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    private function productPayloadItems(Request $request): array
    {
        return $this->payloadItems($request, 'products');
    }

    private function normalizeProductPayload(array $item): array
    {
        return $this->normalizePayload($item, self::PRODUCT_FIELD_MAP);
    }

    private function payloadItems(Request $request, string $collectionKey, array $aliases = []): array
    {
        $payload = $request->all();

        if (array_is_list($payload)) {
            return $payload;
        }

        foreach (array_merge([$collectionKey], $aliases) as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return array_values($payload[$key]);
            }
        }

        foreach (array_merge([$collectionKey], $aliases) as $key) {
            $uppercaseCollectionKey = Str::upper($key);
            if (isset($payload[$uppercaseCollectionKey]) && is_array($payload[$uppercaseCollectionKey])) {
                return array_values($payload[$uppercaseCollectionKey]);
            }
        }

        if (isset($payload['data']) && is_array($payload['data'])) {
            return array_values($payload['data']);
        }

        return [$payload];
    }

    private function normalizePayload(array $item, array $fieldMap): array
    {
        $normalized = [];

        foreach ($item as $key => $value) {
            $normalizedKey = $fieldMap[$key] ?? Str::snake((string) $key);
            $normalized[$normalizedKey] = is_string($value) ? trim($value) : $value;
        }

        foreach (['fecha_susp', 'fecha_hora_creacion', 'fecha_hora_ult_modif'] as $dateField) {
            if (!empty($normalized[$dateField])) {
                $normalized[$dateField] = Carbon::parse($normalized[$dateField])->toDateTimeString();
            }
        }

        if (!empty($normalized['microsip_id'])) {
            $normalized['microsip_id'] = (string) $normalized['microsip_id'];
        }

        foreach ([
            'act_automatica',
            'usar_tabla_factores',
            'agregar_precios',
            'aplica_solo_sobre_impte_imp',
            'causa_flujo_efectivo',
            'diferir_cfdi_cobros',
        ] as $booleanField) {
            if (array_key_exists($booleanField, $normalized)) {
                $normalized[$booleanField] = $this->normalizeBooleanValue($normalized[$booleanField]);
            }
        }

        foreach (['articulo_id', 'clave_articulo_id', 'clave_articulo'] as $stringField) {
            if (!empty($normalized[$stringField]) && is_scalar($normalized[$stringField])) {
                $normalized[$stringField] = (string) $normalized[$stringField];
            }
        }

        return $normalized;
    }

    private function normalizeBooleanValue(mixed $value): ?bool
    {
        if (is_bool($value) || $value === null) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        if (is_string($value)) {
            return match (Str::upper(trim($value))) {
                'S', 'SI', 'Y', 'YES', 'TRUE', 'T', '1' => true,
                'N', 'NO', 'FALSE', 'F', '0', '' => false,
                default => null,
            };
        }

        return null;
    }

    private function requestedSincronizado(Request $request): bool
    {
        if (! $request->has('sincronizado')) {
            return false;
        }

        return (bool) filter_var(
            $request->query('sincronizado'),
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );
    }

    private function doctoVePayload(DoctoVe $docto): array
    {
        return [
            'id' => $docto->id,
            'order_id' => $docto->order_id,
            'sync_status' => $docto->sync_status,
            'sincronizado' => (bool) $docto->sincronizado,
            'validation_errors' => $docto->validation_errors,
            'doctos_ve' => $this->doctoVeHeaderPayload($docto),
            'doctos_ve_detalles' => $docto->detalles->map(
                fn ($detalle) => [
                    'id' => $detalle->id,
                    ...$this->doctoVeDetallePayload($detalle),
                ]
            )->values(),
        ];
    }

    private function doctoVeHeaderPayload(DoctoVe $docto): array
    {
        $payload = [
            'TIPO_DOCTO' => $docto->tipo_docto,
            'SUBTIPO_DOCTO' => $docto->subtipo_docto,
            'SUCURSAL_ID' => $docto->sucursal_id,
            'FOLIO' => $docto->folio,
            'FECHA' => $docto->fecha?->toDateString(),
            'HORA' => $docto->hora,
            'CLAVE_CLIENTE' => $docto->clave_cliente,
            'CLIENTE_ID' => $docto->cliente_id,
            'DIR_CLI_ID' => $docto->dir_cli_id,
            'DIR_CONSIG_ID' => $docto->dir_consig_id ?: $docto->dir_cli_id,
            'ALMACEN_ID' => $docto->almacen_id,
            'LUGAR_EXPEDICION_ID' => $docto->lugar_expedicion_id,
            'MONEDA_ID' => $docto->moneda_id,
            'TIPO_CAMBIO' => (float) $docto->tipo_cambio,
            'TIPO_DSCTO' => $docto->tipo_dscto,
            'DSCTO_PCTJE' => (float) $docto->dscto_pctje,
            'DSCTO_IMPORTE' => (float) $docto->dscto_importe,
            'ESTATUS' => $docto->estatus,
            'APLICADO' => $docto->aplicado,
            'FECHA_VIGENCIA_ENTREGA' => $docto->fecha_vigencia_entrega?->toDateString(),
            'ORDEN_COMPRA' => $docto->orden_compra,
            'FECHA_ORDEN_COMPRA' => $docto->fecha_orden_compra?->toDateString(),
            'FOLIO_RECIBO_MERCANCIA' => $docto->folio_recibo_mercancia,
            'FECHA_RECIBO_MERCANCIA' => $docto->fecha_recibo_mercancia?->toDateString(),
            'DESCRIPCION' => $docto->descripcion,
            'IMPORTE_NETO' => (float) $docto->importe_neto,
            'FLETES' => (float) $docto->fletes,
            'OTROS_CARGOS' => (float) $docto->otros_cargos,
            'TOTAL_IMPUESTOS' => (float) $docto->total_impuestos,
            'TOTAL_RETENCIONES' => (float) $docto->total_retenciones,
            'TOTAL_ANTICIPOS' => (float) $docto->total_anticipos,
            'PESO_EMBARQUE' => (float) $docto->peso_embarque,
            'FORMA_EMITIDA' => $docto->forma_emitida,
            'CONTABILIZADO' => $docto->contabilizado,
            'ACREDITAR_CXC' => $docto->acreditar_cxc,
            'SISTEMA_ORIGEN' => $docto->sistema_origen,
            'COND_PAGO_ID' => $docto->cond_pago_id ?: self::DEFAULT_DOCTO_VE_COND_PAGO_ID,
            'FECHA_DSCTO_PPAG' => $docto->fecha_dscto_ppag?->toDateString(),
            'PCTJE_DSCTO_PPAG' => (float) $docto->pctje_dscto_ppag,
            'VENDEDOR_ID' => $docto->vendedor_id,
            'PCTJE_COMIS' => (float) $docto->pctje_comis,
            'VIA_EMBARQUE_ID' => $docto->via_embarque_id,
            'IMPORTE_COBRO' => (float) $docto->importe_cobro,
            'DESCRIPCION_COBRO' => $docto->descripcion_cobro,
            'IMPUESTO_SUSTITUIDO_ID' => $docto->impuesto_sustituido_id,
            'IMPUESTO_SUSTITUTO_ID' => $docto->impuesto_sustituto_id,
            'USUARIO_CREADOR' => $docto->usuario_creador,
            'ES_CFD' => $docto->es_cfd,
            'MODALIDAD_FACTURACION' => $docto->modalidad_facturacion,
            'ENVIADO' => $docto->enviado,
            'FECHA_HORA_ENVIO' => $docto->fecha_hora_envio?->toISOString(),
            'EMAIL_ENVIO' => $docto->email_envio,
            'CFD_ENVIO_ESPECIAL' => $docto->cfd_envio_especial,
            'USO_CFDI' => $docto->uso_cfdi,
            'METODO_PAGO_SAT' => $docto->metodo_pago_sat,
            'CFDI_CERTIFICADO' => $docto->cfdi_certificado,
            'CFDI_FACT_DEVUELTA_ID' => $docto->cfdi_fact_devuelta_id,
            'FECHA_HORA_CREACION' => $docto->fecha_hora_creacion?->toISOString(),
            'USUARIO_ULT_MODIF' => $docto->usuario_ult_modif,
            'USUARIO_AUT_CREACION' => $docto->usuario_aut_creacion,
            'FECHA_HORA_ULT_MODIF' => $docto->fecha_hora_ult_modif?->toISOString(),
            'CARGAR_SUN' => $docto->cargar_sun,
            'USUARIO_AUT_MODIF' => $docto->usuario_aut_modif,
            'USUARIO_CANCELACION' => $docto->usuario_cancelacion,
            'FECHA_HORA_CANCELACION' => $docto->fecha_hora_cancelacion?->toISOString(),
            'USUARIO_AUT_CANCELACION' => $docto->usuario_aut_cancelacion,
            'PTL' => $docto->ptl,
        ];

        if ($docto->docto_ve_id) {
            $payload = ['DOCTO_VE_ID' => $docto->docto_ve_id] + $payload;
        }

        return $payload;
    }

    private function doctoVeDetallePayload(DoctoVeDetalle $detalle): array
    {
        $payload = [
            'CLAVE_ARTICULO' => $detalle->clave_articulo,
            'ARTICULO_ID' => $detalle->articulo_id,
            'UNIDADES' => (float) $detalle->unidades,
            'UNIDADES_COMPRO' => (float) $detalle->unidades_compro,
            'UNIDADES_SURT_DE' => (float) $detalle->unidades_surt_de,
            'UNIDADES_A_SURTIR' => (float) $detalle->unidades_a_surtir,
            'PRECIO_UNITARIO' => (float) $detalle->precio_unitario,
            'PCTJE_DSCTO' => (float) $detalle->pctje_dscto,
            'DSCTO_ART' => (float) $detalle->dscto_art,
            'PCTJE_DSCTO_CLI' => (float) $detalle->pctje_dscto_cli,
            'DSCTO_EXTRA' => (float) $detalle->dscto_extra,
            'PCTJE_DSCTO_VOL' => (float) $detalle->pctje_dscto_vol,
            'PCTJE_DSCTO_PROM' => (float) $detalle->pctje_dscto_prom,
            'PRECIO_TOTAL_NETO' => (float) $detalle->precio_total_neto,
            'PRECIO_MODIFICADO' => $detalle->precio_modificado,
            'PCTJE_COMIS' => (float) $detalle->pctje_comis,
            'ROL' => $detalle->rol,
            'NOTAS' => $detalle->notas,
            'TERCERO_CO_ID' => $detalle->tercero_co_id,
            'POSICION' => $detalle->posicion,
        ];

        if ($detalle->docto_ve_id) {
            $payload = ['DOCTO_VE_ID' => $detalle->docto_ve_id] + $payload;
        }

        if ($detalle->docto_ve_det_id) {
            $payload = ['DOCTO_VE_DET_ID' => $detalle->docto_ve_det_id] + $payload;
        }

        return $payload;
    }

    private function customerRules(): array
    {
        return [
            'microsip_id' => ['required', 'string', 'max:100'],
            'clave_cliente' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'contacto1' => ['nullable', 'string', 'max:255'],
            'contacto2' => ['nullable', 'string', 'max:255'],
            'estatus' => ['nullable', 'string', 'max:1'],
            'causa_susp' => ['nullable', 'string', 'max:255'],
            'fecha_susp' => ['nullable', 'date'],
            'cobrar_impuestos' => ['nullable', 'string', 'max:1'],
            'retiene_impuestos' => ['nullable', 'string', 'max:1'],
            'sujeto_ieps' => ['nullable', 'string', 'max:1'],
            'generar_intereses' => ['nullable', 'string', 'max:1'],
            'emitir_edocta' => ['nullable', 'string', 'max:1'],
            'diferir_cfdi_cobros' => ['nullable', 'boolean'],
            'limite_credito' => ['nullable', 'numeric', 'min:0'],
            'moneda_id' => ['nullable', 'integer', 'min:0'],
            'cond_pago_id' => ['nullable', 'integer', 'min:0'],
            'tipo_cliente_id' => ['nullable', 'integer', 'min:0'],
            'zona_cliente_id' => ['nullable', 'integer', 'min:0'],
            'cobrador_id' => ['nullable', 'integer', 'min:0'],
            'vendedor_id' => ['nullable', 'integer', 'min:0'],
            'notas' => ['nullable', 'string'],
            'cuenta_cxc' => ['nullable', 'string', 'max:100'],
            'cuenta_anticipos' => ['nullable', 'string', 'max:100'],
            'formatos_email' => ['nullable', 'string', 'max:255'],
            'receptor_cfd' => ['nullable', 'string', 'max:100'],
            'num_prov_cliente' => ['nullable', 'string', 'max:100'],
            'campos_addenda' => ['nullable', 'string'],
            'usuario_creador' => ['nullable', 'string', 'max:100'],
            'fecha_hora_creacion' => ['nullable', 'date'],
            'usuario_aut_creacion' => ['nullable', 'string', 'max:100'],
            'usuario_ult_modif' => ['nullable', 'string', 'max:100'],
            'fecha_hora_ult_modif' => ['nullable', 'date'],
            'usuario_aut_modif' => ['nullable', 'string', 'max:100'],
            'cfdiw_usuario' => ['nullable', 'string', 'max:255'],
            'cfdiw_password' => ['nullable', 'string', 'max:255'],
            'cfdiw_estatus' => ['nullable', 'string', 'max:1'],
            'cdfiw_formato_cfd_ve' => ['nullable', 'string', 'max:255'],
            'cdfiw_formato_cfdi_ve' => ['nullable', 'string', 'max:255'],
            'cdfiw_formato_dev_cfd_ve' => ['nullable', 'string', 'max:255'],
            'cdfiw_formato_dev_cfdi_ve' => ['nullable', 'string', 'max:255'],
            'cdfiw_formato_cfd_pv' => ['nullable', 'string', 'max:255'],
            'cdfiw_formato_cfdi_pv' => ['nullable', 'string', 'max:255'],
            'cdfiw_formato_dev_cfd_pv' => ['nullable', 'string', 'max:255'],
            'cdfiw_formato_dev_cfdi_pv' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function dirClienteRules(): array
    {
        return [
            'dir_cli_id' => ['required', 'integer', 'min:1'],
            'cliente_id' => ['required', 'integer', 'min:1'],
            'nombre_consig' => ['required', 'string', 'max:200'],
            'calle' => ['nullable', 'string', 'max:430'],
            'nombre_calle' => ['nullable', 'string', 'max:100'],
            'num_exterior' => ['nullable', 'string', 'max:10'],
            'num_interior' => ['nullable', 'string', 'max:10'],
            'colonia' => ['nullable', 'string', 'max:100'],
            'colonia_clave_fiscal' => ['nullable', 'string', 'max:4'],
            'poblacion' => ['nullable', 'string', 'max:100'],
            'poblacion_clave_fisc' => ['nullable', 'string', 'max:3'],
            'referencia' => ['nullable', 'string', 'max:100'],
            'ciudad_id' => ['nullable', 'integer', 'min:0'],
            'estado_id' => ['nullable', 'integer', 'min:0'],
            'codigo_postal' => ['nullable', 'string', 'max:10'],
            'pais_id' => ['nullable', 'integer', 'min:0'],
            'telefono1' => ['nullable', 'string', 'max:35'],
            'telefono2' => ['nullable', 'string', 'max:35'],
            'fax' => ['nullable', 'string', 'max:35'],
            'email' => ['nullable', 'string', 'max:200'],
            'rfc_curp' => ['nullable', 'string', 'max:18'],
            'tipo_persona' => ['nullable', 'string', 'max:1'],
            'clave_regimen_fiscal' => ['nullable', 'string', 'max:3'],
            'tax_id' => ['nullable', 'string', 'max:40'],
            'contacto' => ['nullable', 'string', 'max:50'],
            'via_embarque_id' => ['nullable', 'integer', 'min:0'],
            'es_dir_ppal' => ['nullable', 'string', 'max:1'],
            'usar_para_envios' => ['nullable', 'string', 'max:1'],
            'usar_para_facturar' => ['nullable', 'string', 'max:1'],
            'gln' => ['nullable', 'string', 'max:20'],
        ];
    }

    private function categoryRules(): array
    {
        return [
            'grupo_linea_id' => ['required', 'integer', 'min:0'],
            'name' => ['required', 'string', 'max:50'],
            'cuenta_almacen' => ['nullable', 'string', 'max:30'],
            'cuenta_costo_venta' => ['nullable', 'string', 'max:30'],
            'cuenta_ventas' => ['nullable', 'string', 'max:30'],
            'cuenta_dscto_ventas' => ['nullable', 'string', 'max:30'],
            'cuenta_devol_ventas' => ['nullable', 'string', 'max:30'],
            'cuenta_compras' => ['nullable', 'string', 'max:30'],
            'cuenta_devol_compras' => ['nullable', 'string', 'max:30'],
            'aplicar_factor_venta' => ['required', 'string', 'max:1'],
            'factor_venta' => ['required', 'numeric'],
            'es_predet' => ['nullable', 'string', 'max:1'],
            'oculto' => ['required', 'string', 'max:1'],
            'usuario_creador' => ['nullable', 'string', 'max:31'],
            'fecha_hora_creacion' => ['nullable', 'date'],
            'usuario_aut_creacion' => ['nullable', 'string', 'max:31'],
            'usuario_ult_modif' => ['nullable', 'string', 'max:31'],
            'fecha_hora_ult_modif' => ['nullable', 'date'],
            'usuario_aut_modif' => ['nullable', 'string', 'max:31'],
        ];
    }

    private function familyRules(): array
    {
        return [
            'linea_articulo_id' => ['required', 'integer', 'min:0'],
            'name' => ['required', 'string', 'max:50'],
            'grupo_linea_id' => ['required', 'integer', 'min:0'],
            'cuenta_almacen' => ['nullable', 'string', 'max:30'],
            'cuenta_costo_venta' => ['nullable', 'string', 'max:30'],
            'cuenta_ventas' => ['nullable', 'string', 'max:30'],
            'cuenta_dscto_ventas' => ['nullable', 'string', 'max:30'],
            'cuenta_devol_ventas' => ['nullable', 'string', 'max:30'],
            'cuenta_compras' => ['nullable', 'string', 'max:30'],
            'cuenta_devol_compras' => ['nullable', 'string', 'max:30'],
            'aplicar_factor_venta' => ['required', 'string', 'max:1'],
            'factor_venta' => ['required', 'numeric'],
            'es_predet' => ['nullable', 'string', 'max:1'],
            'oculto' => ['required', 'string', 'max:1'],
            'usuario_creador' => ['nullable', 'string', 'max:31'],
            'fecha_hora_creacion' => ['nullable', 'date'],
            'usuario_aut_creacion' => ['nullable', 'string', 'max:31'],
            'usuario_ult_modif' => ['nullable', 'string', 'max:31'],
            'fecha_hora_ult_modif' => ['nullable', 'date'],
            'usuario_aut_modif' => ['nullable', 'string', 'max:31'],
        ];
    }

    private function productRules(): array
    {
        return [
            'microsip_id' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'family_id' => ['nullable', 'integer', 'exists:families,id'],
            'sku' => ['nullable', 'string', 'max:255'],
            'default_price' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'brand' => ['nullable', 'string', 'max:255'],
            'keyword' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'processed' => ['nullable', 'boolean'],
            'es_almacenable' => ['nullable', 'string', 'max:1'],
            'es_juego' => ['nullable', 'string', 'max:1'],
            'estatus' => ['nullable', 'string', 'max:1'],
            'causa_susp' => ['nullable', 'string', 'max:255'],
            'fecha_susp' => ['nullable', 'date'],
            'imprimir_comp' => ['nullable', 'string', 'max:1'],
            'permitir_agregar_comp' => ['nullable', 'string', 'max:1'],
            'linea_articulo_id' => ['nullable', 'integer', 'min:0'],
            'unidad_venta' => ['nullable', 'string', 'max:100'],
            'unidad_compra' => ['nullable', 'string', 'max:100'],
            'contenido_unidad_compra' => ['nullable', 'numeric'],
            'peso_unitario' => ['nullable', 'numeric'],
            'es_peso_variable' => ['nullable', 'string', 'max:1'],
            'seguimiento' => ['nullable', 'string', 'max:1'],
            'dias_garantia' => ['nullable', 'integer', 'min:0'],
            'es_importado' => ['nullable', 'string', 'max:1'],
            'es_siempre_importado' => ['nullable', 'string', 'max:1'],
            'pctje_arancel' => ['nullable', 'numeric'],
            'notas_compras' => ['nullable', 'string'],
            'imprimir_notas_compras' => ['nullable', 'string', 'max:1'],
            'notas_ventas' => ['nullable', 'string'],
            'imprimir_notas_ventas' => ['nullable', 'string', 'max:1'],
            'es_precio_variable' => ['nullable', 'string', 'max:1'],
            'cuenta_almacen' => ['nullable', 'string', 'max:100'],
            'cuenta_costo_venta' => ['nullable', 'string', 'max:100'],
            'cuenta_ventas' => ['nullable', 'string', 'max:100'],
            'cuenta_dscto_ventas' => ['nullable', 'string', 'max:100'],
            'cuenta_devol_ventas' => ['nullable', 'string', 'max:100'],
            'cuenta_compras' => ['nullable', 'string', 'max:100'],
            'cuenta_devol_compras' => ['nullable', 'string', 'max:100'],
            'aplicar_factor_venta' => ['nullable', 'string', 'max:1'],
            'factor_venta' => ['nullable', 'numeric'],
            'red_precio_con_impto' => ['nullable', 'string', 'max:1'],
            'factor_red_precio_con_impto' => ['nullable', 'numeric'],
            'usuario_creador' => ['nullable', 'string', 'max:100'],
            'fecha_hora_creacion' => ['nullable', 'date'],
            'usuario_aut_creacion' => ['nullable', 'string', 'max:100'],
            'usuario_ult_modif' => ['nullable', 'string', 'max:100'],
            'fecha_hora_ult_modif' => ['nullable', 'date'],
            'usuario_aut_modif' => ['nullable', 'string', 'max:100'],
        ];
    }

    private function claveArticuloRules(): array
    {
        return [
            'clave_articulo_id' => ['required', 'string', 'max:100'],
            'clave_articulo' => ['required', 'string', 'max:255'],
            'articulo_id' => ['required', 'string', 'max:100'],
            'rol_clave_art_id' => ['nullable', 'integer', 'min:0'],
            'contenido_empaque' => ['nullable', 'numeric'],
        ];
    }

    private function claveClienteRules(): array
    {
        return [
            'clave_cliente_id' => ['required', 'integer', 'min:0'],
            'clave_cliente' => ['required', 'string', 'max:20'],
            'cliente_id' => ['required', 'integer', 'min:0'],
            'rol_clave_cli_id' => ['required', 'integer', 'min:0'],
        ];
    }

    private function precioArticuloRules(): array
    {
        return [
            'precio_articulo_id' => ['required', 'integer', 'min:0'],
            'articulo_id' => ['required', 'string', 'max:100'],
            'precio_empresa_id' => ['required', 'integer', 'min:0'],
            'precio' => ['required', 'numeric'],
            'moneda_id' => ['required', 'integer', 'min:0'],
            'margen' => ['required', 'numeric'],
            'markup' => ['required', 'numeric'],
            'fecha_hora_ult_modif' => ['nullable', 'date'],
        ];
    }

    private function precioEmpresaRules(): array
    {
        return [
            'precio_empresa_id' => ['required', 'integer', 'min:0'],
            'nombre' => ['required', 'string', 'max:30'],
            'id_interno' => ['nullable', 'string', 'max:1'],
            'act_automatica' => ['required', 'boolean'],
            'precio_empresa_act_autc' => ['nullable', 'integer', 'min:0'],
            'porcentaje' => ['required', 'numeric'],
            'usar_tabla_factores' => ['required', 'boolean'],
            'factor_redondeo' => ['required', 'numeric'],
            'agregar_precios' => ['required', 'boolean'],
            'posicion' => ['required', 'integer'],
            'usuario_creador' => ['nullable', 'string', 'max:31'],
            'fecha_hora_creacion' => ['nullable', 'date'],
            'usuario_aut_creacion' => ['nullable', 'string', 'max:31'],
            'usuario_ult_modif' => ['nullable', 'string', 'max:31'],
            'fecha_hora_ult_modif' => ['nullable', 'date'],
            'usuario_aut_modif' => ['nullable', 'string', 'max:31'],
        ];
    }

    private function precioCliCliRules(): array
    {
        return [
            'precio_cli_cli_id' => ['required', 'integer', 'min:1'],
            'politica_precios_cli_id' => ['nullable', 'integer', 'min:0'],
            'clave_cliente' => ['nullable', 'string', 'max:20'],
            'cliente_id' => ['required', 'integer', 'min:1'],
            'precio_empresa_id' => ['required', 'integer', 'min:1'],
            'politica_dscto_art_cli_id' => ['required', 'integer', 'min:0'],
        ];
    }

    private function tipoImpuestoRules(): array
    {
        return [
            'tipo_impto_id' => ['required', 'integer', 'min:0'],
            'nombre' => ['required', 'string', 'max:30'],
            'tipo' => ['required', 'string', 'max:1'],
            'grava_otros_imptos' => ['nullable', 'string', 'max:1'],
            'aplica_solo_sobre_impte_imp' => ['required', 'boolean'],
            'id_interno' => ['nullable', 'string', 'max:1'],
            'es_predet' => ['nullable', 'string', 'max:1'],
            'usuario_creador' => ['nullable', 'string', 'max:31'],
            'fecha_hora_creacion' => ['nullable', 'date'],
            'usuario_aut_creacion' => ['nullable', 'string', 'max:31'],
            'usuario_ult_modif' => ['nullable', 'string', 'max:31'],
            'fecha_hora_ult_modif' => ['nullable', 'date'],
            'usuario_aut_modif' => ['nullable', 'string', 'max:31'],
        ];
    }

    private function impuestoRules(): array
    {
        return [
            'impuesto_id' => ['required', 'integer', 'min:0'],
            'tipo_impto_id' => ['required', 'integer', 'min:0'],
            'nombre' => ['required', 'string', 'max:30'],
            'tipo_calc' => ['required', 'string', 'max:1'],
            'pctje_impuesto' => ['required', 'numeric'],
            'importe_unitario' => ['required', 'numeric'],
            'unidad_impto' => ['nullable', 'string', 'max:20'],
            'es_predet' => ['nullable', 'string', 'max:1'],
            'oculto' => ['required', 'string', 'max:1'],
            'causa_flujo_efectivo' => ['required', 'boolean'],
            'cuenta_pend_en_ventas' => ['nullable', 'string', 'max:30'],
            'cuenta_en_ventas' => ['nullable', 'string', 'max:30'],
            'cuenta_pend_en_compras' => ['nullable', 'string', 'max:30'],
            'cuenta_en_compras' => ['nullable', 'string', 'max:30'],
            'tipo_iva' => ['nullable', 'string', 'max:1'],
            'usuario_creador' => ['nullable', 'string', 'max:31'],
            'fecha_hora_creacion' => ['nullable', 'date'],
            'usuario_aut_creacion' => ['nullable', 'string', 'max:31'],
            'usuario_ult_modif' => ['nullable', 'string', 'max:31'],
            'fecha_hora_ult_modif' => ['nullable', 'date'],
            'usuario_aut_modif' => ['nullable', 'string', 'max:31'],
        ];
    }

    private function impuestoArticuloRules(): array
    {
        return [
            'impuesto_art_id' => ['required', 'integer', 'min:0'],
            'articulo_id' => ['required', 'string', 'max:100'],
            'impuesto_id' => ['required', 'integer', 'min:0'],
            'unidades_impuesto' => ['required', 'numeric'],
            'tipo_seleccion' => ['required', 'string', 'max:1'],
            'conjunto_sucursales_id' => ['nullable', 'integer', 'min:0'],
        ];
    }

    private function productUpsertPayload(array $validated, ?Product $existingProduct, int $defaultCategoryId): array
    {
        $payload = $validated;
        $payload['category_id'] = $validated['category_id']
            ?? $existingProduct?->category_id
            ?? $defaultCategoryId;

        if (!array_key_exists('family_id', $payload) && array_key_exists('linea_articulo_id', $validated)) {
            $family = Family::query()
                ->where('linea_articulo_id', (int) $validated['linea_articulo_id'])
                ->first();

            if ($family) {
                $payload['family_id'] = $family->id;
                $payload['category_id'] = $family->category_id;
            }
        }

        $payload['sku'] = $validated['sku'] ?? $existingProduct?->sku ?? 'MS-' . $validated['microsip_id'];

        if (!array_key_exists('default_price', $payload)) {
            $payload['default_price'] = $existingProduct?->default_price ?? 0;
        }

        if (array_key_exists('estatus', $payload)) {
            $payload['is_active'] = $payload['estatus'] === 'A';
        } elseif (!array_key_exists('is_active', $payload)) {
            $payload['is_active'] = $existingProduct?->is_active ?? true;
        }

        if (!array_key_exists('processed', $payload)) {
            $payload['processed'] = $existingProduct?->processed ?? false;
        }

        return $payload;
    }

    private function familyUpsertPayload(array $validated, Category $category, ?Family $existingFamily): array
    {
        $payload = $validated;
        $payload['category_id'] = $category->id;
        $payload['grupo_linea_id'] = $category->grupo_linea_id;

        if (array_key_exists('oculto', $validated)) {
            $payload['is_active'] = Str::upper((string) $validated['oculto']) !== 'S';
        } elseif (!array_key_exists('is_active', $payload)) {
            $payload['is_active'] = $existingFamily?->is_active ?? true;
        }

        return $payload;
    }

    private function categoryUpsertPayload(array $validated, ?Category $existingCategory): array
    {
        $payload = $validated;
        $payload['code'] = $existingCategory?->code ?? 'GL-' . $validated['grupo_linea_id'];

        if (array_key_exists('oculto', $validated)) {
            $payload['is_active'] = Str::upper((string) $validated['oculto']) !== 'S';
        } elseif (!array_key_exists('is_active', $payload)) {
            $payload['is_active'] = $existingCategory?->is_active ?? true;
        }

        return $payload;
    }

    private function claveArticuloUpsertPayload(array $validated, Product $product): array
    {
        return [
            'product_id' => $product->id,
            'clave_articulo_id' => (string) $validated['clave_articulo_id'],
            'clave_articulo' => (string) $validated['clave_articulo'],
            'articulo_id' => (string) $validated['articulo_id'],
            'rol_clave_art_id' => $validated['rol_clave_art_id'] ?? null,
            'contenido_empaque' => $validated['contenido_empaque'] ?? null,
        ];
    }

    private function claveClienteUpsertPayload(array $validated): array
    {
        return [
            'clave_cliente_id' => (int) $validated['clave_cliente_id'],
            'clave_cliente' => (string) $validated['clave_cliente'],
            'cliente_id' => (int) $validated['cliente_id'],
            'rol_clave_cli_id' => (int) $validated['rol_clave_cli_id'],
        ];
    }

    private function precioArticuloUpsertPayload(array $validated, Product $product): array
    {
        return [
            'product_id' => $product->id,
            'precio_articulo_id' => (int) $validated['precio_articulo_id'],
            'articulo_id' => (string) $validated['articulo_id'],
            'precio_empresa_id' => (int) $validated['precio_empresa_id'],
            'precio' => $validated['precio'],
            'moneda_id' => (int) $validated['moneda_id'],
            'margen' => $validated['margen'],
            'markup' => $validated['markup'],
            'fecha_hora_ult_modif' => $validated['fecha_hora_ult_modif'] ?? null,
        ];
    }

    private function precioEmpresaUpsertPayload(array $validated): array
    {
        return [
            'precio_empresa_id' => (int) $validated['precio_empresa_id'],
            'nombre' => (string) $validated['nombre'],
            'id_interno' => $validated['id_interno'] ?? null,
            'act_automatica' => (bool) $validated['act_automatica'],
            'precio_empresa_act_autc' => $validated['precio_empresa_act_autc'] ?? null,
            'porcentaje' => $validated['porcentaje'],
            'usar_tabla_factores' => (bool) $validated['usar_tabla_factores'],
            'factor_redondeo' => $validated['factor_redondeo'],
            'agregar_precios' => (bool) $validated['agregar_precios'],
            'posicion' => (int) $validated['posicion'],
            'usuario_creador' => $validated['usuario_creador'] ?? null,
            'fecha_hora_creacion' => $validated['fecha_hora_creacion'] ?? null,
            'usuario_aut_creacion' => $validated['usuario_aut_creacion'] ?? null,
            'usuario_ult_modif' => $validated['usuario_ult_modif'] ?? null,
            'fecha_hora_ult_modif' => $validated['fecha_hora_ult_modif'] ?? null,
            'usuario_aut_modif' => $validated['usuario_aut_modif'] ?? null,
        ];
    }

    private function precioCliCliUpsertPayload(array $validated): array
    {
        return [
            'precio_cli_cli_id' => (int) $validated['precio_cli_cli_id'],
            'politica_precios_cli_id' => $validated['politica_precios_cli_id'] ?? null,
            'clave_cliente' => $validated['clave_cliente'] ?? null,
            'cliente_id' => (int) $validated['cliente_id'],
            'precio_empresa_id' => (int) $validated['precio_empresa_id'],
            'politica_dscto_art_cli_id' => (int) $validated['politica_dscto_art_cli_id'],
        ];
    }

    private function tipoImpuestoUpsertPayload(array $validated): array
    {
        return [
            'tipo_impto_id' => (int) $validated['tipo_impto_id'],
            'nombre' => (string) $validated['nombre'],
            'tipo' => (string) $validated['tipo'],
            'grava_otros_imptos' => $validated['grava_otros_imptos'] ?? null,
            'aplica_solo_sobre_impte_imp' => (bool) $validated['aplica_solo_sobre_impte_imp'],
            'id_interno' => $validated['id_interno'] ?? null,
            'es_predet' => $validated['es_predet'] ?? null,
            'usuario_creador' => $validated['usuario_creador'] ?? null,
            'fecha_hora_creacion' => $validated['fecha_hora_creacion'] ?? null,
            'usuario_aut_creacion' => $validated['usuario_aut_creacion'] ?? null,
            'usuario_ult_modif' => $validated['usuario_ult_modif'] ?? null,
            'fecha_hora_ult_modif' => $validated['fecha_hora_ult_modif'] ?? null,
            'usuario_aut_modif' => $validated['usuario_aut_modif'] ?? null,
        ];
    }

    private function impuestoUpsertPayload(array $validated): array
    {
        return [
            'impuesto_id' => (int) $validated['impuesto_id'],
            'tipo_impto_id' => (int) $validated['tipo_impto_id'],
            'nombre' => (string) $validated['nombre'],
            'tipo_calc' => (string) $validated['tipo_calc'],
            'pctje_impuesto' => $validated['pctje_impuesto'],
            'importe_unitario' => $validated['importe_unitario'],
            'unidad_impto' => $validated['unidad_impto'] ?? null,
            'es_predet' => $validated['es_predet'] ?? null,
            'oculto' => (string) $validated['oculto'],
            'causa_flujo_efectivo' => (bool) $validated['causa_flujo_efectivo'],
            'cuenta_pend_en_ventas' => $validated['cuenta_pend_en_ventas'] ?? null,
            'cuenta_en_ventas' => $validated['cuenta_en_ventas'] ?? null,
            'cuenta_pend_en_compras' => $validated['cuenta_pend_en_compras'] ?? null,
            'cuenta_en_compras' => $validated['cuenta_en_compras'] ?? null,
            'tipo_iva' => $validated['tipo_iva'] ?? null,
            'usuario_creador' => $validated['usuario_creador'] ?? null,
            'fecha_hora_creacion' => $validated['fecha_hora_creacion'] ?? null,
            'usuario_aut_creacion' => $validated['usuario_aut_creacion'] ?? null,
            'usuario_ult_modif' => $validated['usuario_ult_modif'] ?? null,
            'fecha_hora_ult_modif' => $validated['fecha_hora_ult_modif'] ?? null,
            'usuario_aut_modif' => $validated['usuario_aut_modif'] ?? null,
        ];
    }

    private function impuestoArticuloUpsertPayload(array $validated, Product $product): array
    {
        return [
            'product_id' => $product->id,
            'impuesto_art_id' => (int) $validated['impuesto_art_id'],
            'articulo_id' => (string) $validated['articulo_id'],
            'impuesto_id' => (int) $validated['impuesto_id'],
            'unidades_impuesto' => $validated['unidades_impuesto'],
            'tipo_seleccion' => (string) $validated['tipo_seleccion'],
            'conjunto_sucursales_id' => $validated['conjunto_sucursales_id'] ?? null,
        ];
    }

    private function customerUpsertPayload(array $validated, ?User $existingUser): array
    {
        $payload = $validated;
        $claveCliente = $validated['clave_cliente'] ?? null;
        $login = $claveCliente ?: ($validated['username'] ?? null) ?: $existingUser?->username ?: (string) $validated['microsip_id'];
        $emailKey = Str::slug((string) $login) ?: (string) $validated['microsip_id'];
        $fallbackEmail = 'cliente-ms-' . $emailKey . '@microsip.local';

        unset($payload['clave_cliente']);

        $payload['role_id'] = User::ROLE_CLIENTE;
        $payload['username'] = $login;
        $payload['email'] = $validated['email']
            ?? ($existingUser?->email && ! Str::endsWith($existingUser->email, '@microsip.local')
                ? $existingUser->email
                : $fallbackEmail);

        if (!$existingUser) {
            $payload['password'] = Hash::make((string) $login);
            $payload['must_change_password'] = true;
        }

        return $payload;
    }

    private function dirClienteUpsertPayload(array $validated, User $user, ?UserAddress $existingAddress): array
    {
        $esDirPpal = Str::upper((string) ($validated['es_dir_ppal'] ?? 'N')) === 'S';
        $calle = $validated['calle'] ?? $validated['nombre_calle'] ?? '';
        $nombreConsig = $validated['nombre_consig'];

        return array_merge($validated, [
            'user_id' => $user->id,
            'cliente_id' => (int) $validated['cliente_id'],
            'dir_cli_id' => (int) $validated['dir_cli_id'],
            'es_dir_ppal' => $esDirPpal ? 'S' : 'N',
            'usar_para_envios' => Str::upper((string) ($validated['usar_para_envios'] ?? 'S')) ?: 'S',
            'usar_para_facturar' => Str::upper((string) ($validated['usar_para_facturar'] ?? 'S')) ?: 'S',
            'alias' => Str::substr($nombreConsig, 0, 100) ?: 'Direccion ' . $validated['dir_cli_id'],
            'contact_name' => Str::substr($validated['contacto'] ?? $nombreConsig, 0, 150),
            'street' => Str::substr($calle, 0, 150),
            'external_number' => $validated['num_exterior'] ?? null,
            'internal_number' => $validated['num_interior'] ?? null,
            'neighborhood' => $validated['colonia'] ?? null,
            'zip_code' => $validated['codigo_postal'] ?? null,
            'city' => $validated['poblacion'] ?? $existingAddress?->city,
            'state' => isset($validated['estado_id']) ? (string) $validated['estado_id'] : $existingAddress?->state,
            'references' => $validated['referencia'] ?? null,
            'phone' => $validated['telefono1'] ?? $validated['telefono2'] ?? null,
            'is_default' => $esDirPpal,
        ]);
    }

    private function syncCustomerProfile(User $user, array $validated): void
    {
        $status = match ($validated['estatus'] ?? null) {
            'A' => CustomerProfile::STATUS_ACTIVO,
            'B' => CustomerProfile::STATUS_BAJA,
            default => CustomerProfile::STATUS_SUSPENDIDO_CREDITO,
        };

        $user->customerProfile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'commercial_name' => $validated['name'],
                'id_microsip' => $validated['microsip_id'],
                'status' => $status,
                'credit_limit' => $validated['limite_credito'] ?? 0,
                'assigned_seller_id' => $validated['vendedor_id'] ?? null,
                'route' => isset($validated['zona_cliente_id']) ? (string) $validated['zona_cliente_id'] : null,
                'notes' => $validated['notas'] ?? null,
            ]
        );
    }

    private function defaultMicrosipCategoryId(): int
    {
        $category = Category::firstOrCreate(
            ['code' => 'MS'],
            [
                'name' => 'Microsip',
                'slug' => 'microsip',
                'is_active' => true,
            ]
        );

        return (int) $category->id;
    }
}
