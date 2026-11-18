<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\EmpresaSucursal;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\QhantuyQrCashierAssignmentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        $users = User::withTrashed()->paginate();

        return view('user.index', compact('users'))
            ->with('i', (request()->input('page', 1) - 1) * $users->perPage());
    }

    public function empresas()
    {
        return view('user.empresas');
    }

    public function create()
    {
        $user = new User();
        $roles = Role::all();
        $empresas = Empresa::query()->orderBy('codigo_cliente')->get();
        $sucursales = Sucursal::query()->orderBy('codigoSucursal')->orderBy('puntoVenta')->get();

        $sucursalesEmpresa = $this->companyBranchOptions();

        return view('user.create', compact('user', 'roles', 'empresas', 'sucursales', 'sucursalesEmpresa'));
    }

    public function store(Request $request)
    {
        $normalizedAlias = $this->normalizeAlias($request->input('alias'));

        $request->validate([
            'name' => 'required|string|max:255',
            'alias' => 'required|string|max:255|unique:users,alias',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'ciudad' => 'required|string|max:255',
            'ci' => 'nullable|string|max:255',
            'provincia_origen' => 'nullable|string|max:255',
            'empresa_id' => ['bail', 'required', 'integer', 'exists:empresa,id'],
            'empresa_sucursal_id' => EmpresaSucursal::assignmentRules($request->input('empresa_id')),
            'sucursal_id' => 'nullable|integer|exists:sucursales,id',
            'roles' => 'nullable|array',
            'roles.*' => 'integer|exists:roles,id',
        ], $this->companyBranchMessages());

        $this->ensureAliasIsAvailable($normalizedAlias);

        $user = new User();
        $user->name = $request->name;
        $user->alias = $normalizedAlias;
        $user->email = $request->email;
        $user->password = bcrypt($request->password);
        $user->ciudad = strtoupper(trim((string) $request->ciudad));
        $user->regionales = [$user->ciudad];
        $user->provincia_origen = $this->normalizeNullableUppercase($request->input('provincia_origen'));
        $user->ci = $request->filled('ci') ? trim((string) $request->ci) : null;
        $user->empresa_id = (int) $request->input('empresa_id');
        $user->empresa_sucursal_id = (int) $request->input('empresa_sucursal_id');
        $user->sucursal_id = $request->filled('sucursal_id') ? (int) $request->sucursal_id : null;
        $user->save();

        if ($request->filled('roles')) {
            $roleNames = Role::whereIn('id', $request->roles)->pluck('name')->toArray();
            $user->syncRoles($roleNames);
        }

        return redirect()->route('users.index')
            ->with('success', 'Usuario creado correctamente');
    }

    public function show($id)
    {
        $user = User::find($id);

        return view('user.show', compact('user'));
    }

    public function edit(Request $request, QhantuyQrCashierAssignmentService $qrAssignments, $id)
    {
        $user = User::findOrFail($id);
        $roles = Role::all();
        $empresas = Empresa::query()->orderBy('codigo_cliente')->get();
        $sucursales = Sucursal::query()->orderBy('codigoSucursal')->orderBy('puntoVenta')->get();
        $sucursalesEmpresa = $this->companyBranchOptions();
        $qrCashier = null;
        $canManageCashierSettings = $this->canManageQrCashierAssignment($request);
        $canConfigureCardPayment = $canManageCashierSettings
            && $user->can('feature.dashboard.facturacion')
            && app(\App\Support\FacturacionModePolicy::class)->roleAllows($user, 'qr_factura');
        if ($canManageCashierSettings) {
            $cashier = $qrAssignments->cashierContext($user);
            $qrCashier = [
                'context' => $cashier,
                'context_hash' => $cashier === null ? null : $this->qrCashierContextHash($cashier),
                'configured' => null,
                'error' => null,
            ];

            if ($cashier !== null) {
                try {
                    $qrCashier['configured'] = $qrAssignments->configured($cashier);
                } catch (RuntimeException $e) {
                    $qrCashier['error'] = $e->getMessage();
                }
            }
        }

        return view('user.edit', compact(
            'user',
            'roles',
            'empresas',
            'sucursales',
            'sucursalesEmpresa',
            'qrCashier',
            'canConfigureCardPayment'
        ));
    }

    public function updateQrCashierAssignment(
        Request $request,
        User $user,
        QhantuyQrCashierAssignmentService $qrAssignments,
    ): RedirectResponse {
        return $this->saveQrCashierAssignment($request, $user, $qrAssignments, false);
    }

    public function destroyQrCashierAssignment(
        Request $request,
        User $user,
        QhantuyQrCashierAssignmentService $qrAssignments,
    ): RedirectResponse {
        return $this->saveQrCashierAssignment($request, $user, $qrAssignments, true);
    }

    private function saveQrCashierAssignment(
        Request $request,
        User $user,
        QhantuyQrCashierAssignmentService $qrAssignments,
        bool $useGeneralCashier,
    ): RedirectResponse {
        abort_unless($this->canManageQrCashierAssignment($request), 403);

        $rules = ['qr_cashier_context' => ['required', 'string', 'size:64']];
        if (! $useGeneralCashier) {
            $rules['qhantuy_qr_appkey'] = ['required', 'string', 'max:500'];
        }
        $validated = $request->validate($rules, [], ['qhantuy_qr_appkey' => 'appkey de Qhantuy']);
        $cashier = $qrAssignments->cashierContext($user);
        $redirect = redirect()->route('users.edit', $user)->withFragment('caja-qr');

        if ($cashier === null || ! hash_equals($this->qrCashierContextHash($cashier), $validated['qr_cashier_context'])) {
            return $redirect->with('qr_cashier_error', 'La sucursal o el punto de venta cambió. Revisa la asignación y vuelve a intentar.');
        }

        try {
            if ($useGeneralCashier) {
                $qrAssignments->clear($cashier);
                $message = 'Los próximos cobros QR usarán la caja general.';
            } else {
                $qrAssignments->save($cashier, trim($validated['qhantuy_qr_appkey']));
                $message = 'Caja QR asignada correctamente.';
            }
        } catch (RuntimeException $e) {
            return $redirect->with('qr_cashier_error', $e->getMessage());
        }

        return $redirect->with('qr_cashier_success', $message);
    }

    private function canManageQrCashierAssignment(Request $request): bool
    {
        return $request->user()?->can('admin-only-menu') === true
            && ! $request->session()->has('impersonator_id')
            && ! $request->session()->has('impersonator_cliente_admin_id');
    }

    private function qrCashierContextHash(array $cashier): string
    {
        return hash('sha256', implode("\0", $cashier));
    }

    public function update(Request $request, User $user)
    {
        $normalizedAlias = $this->normalizeAlias($request->input('alias'));
        $canManageCashierSettings = $this->canManageQrCashierAssignment($request);
        if ($request->exists('facturacionTarjetaNfc')) {
            abort_unless($canManageCashierSettings, 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'alias' => 'required|string|max:255|unique:users,alias,' . $user->id,
            'email' => 'required|email|unique:users,email,' . $user->id,
            'ciudad' => 'required|string|max:255',
            'ci' => 'nullable|string|max:255',
            'provincia_origen' => 'nullable|string|max:255',
            'empresa_id' => ['bail', 'required', 'integer', 'exists:empresa,id'],
            'empresa_sucursal_id' => EmpresaSucursal::assignmentRules($request->input('empresa_id')),
            'sucursal_id' => 'nullable|integer|exists:sucursales,id',
            'roles' => 'nullable|array',
            'roles.*' => 'integer|exists:roles,id',
            'password' => 'nullable|min:8',
            'facturacionTarjetaNfc' => 'nullable|boolean',
        ], $this->companyBranchMessages());

        $this->ensureAliasIsAvailable($normalizedAlias, $user->id);

        $user->name = $request->name;
        $user->alias = $normalizedAlias;
        $user->email = $request->email;
        $user->ciudad = strtoupper(trim((string) $request->ciudad));
        $user->regionales = [$user->ciudad];
        $user->provincia_origen = $this->normalizeNullableUppercase($request->input('provincia_origen'));
        if ($request->filled('ci')) {
            $user->ci = trim((string) $request->ci);
        }
        $user->empresa_id = (int) $request->input('empresa_id');
        $user->empresa_sucursal_id = (int) $request->input('empresa_sucursal_id');
        $user->sucursal_id = $request->filled('sucursal_id') ? (int) $request->sucursal_id : null;

        if ($request->filled('password')) {
            $user->password = bcrypt($request->password);
        }

        $user->save();

        $roleNames = $request->filled('roles')
            ? Role::whereIn('id', $request->roles)->pluck('name')->toArray()
            : [];
        $user->syncRoles($roleNames);

        if ($canManageCashierSettings) {
            $cardPermission = Permission::findOrCreate(\App\Support\FacturacionModePolicy::CARD_NFC_USER_PERMISSION, 'web');
            if (! $user->can('feature.dashboard.facturacion')
                || ! app(\App\Support\FacturacionModePolicy::class)->roleAllows($user, 'qr_factura')) {
                $user->revokePermissionTo($cardPermission);
            } elseif ($request->exists('facturacionTarjetaNfc')) {
                if ($request->boolean('facturacionTarjetaNfc')) {
                    $user->givePermissionTo($cardPermission);
                } else {
                    $user->revokePermissionTo($cardPermission);
                }
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return redirect()->route('users.index')
            ->with('success', 'Usuario actualizado correctamente');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'Usuario dado de baja correctamente');
    }

    public function restore($id)
    {
        $user = User::withTrashed()->findOrFail($id);
        $user->restore();

        return redirect()->route('users.index')
            ->with('success', 'Usuario reactivado correctamente');
    }

    public function excel(?bool $forceOnlyWithEmpresa = null)
    {
        $onlyWithEmpresa = $forceOnlyWithEmpresa ?? request()->boolean('only_with_empresa');
        $empresaId = request()->filled('empresa_id') ? (int) request()->input('empresa_id') : null;
        $codigoCliente = trim((string) request()->input('codigo_cliente', ''));

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Usuarios');

        $headers = ['#', 'Nombre', 'Alias', 'Email', 'Regional', 'Provincia origen', 'CI', 'Empresa', 'Sucursal de Facturación', 'Roles', 'Estado', 'Sucursal de empresa'];
        $sheet->fromArray($headers, null, 'A1');

        $users = $this->usersReportQuery($onlyWithEmpresa, $empresaId, $codigoCliente)->get();
        $row = 2;

        foreach ($users as $index => $user) {
            $sheet->fromArray([
                $index + 1,
                $user->name,
                $user->alias,
                $user->email,
                $user->regionalesTexto(),
                $user->provincia_origen,
                $user->ci,
                $user->empresa ? trim($user->empresa->codigo_cliente . ' - ' . $user->empresa->nombre) : '',
                $user->sucursal ? 'Suc. ' . $user->sucursal->codigoSucursal . ' / PV ' . $user->sucursal->puntoVenta . ' - ' . $user->sucursal->municipio : '',
                $user->roles->pluck('name')->implode(', '),
                $user->trashed() ? 'Inactivo' : 'Activo',
                $user->empresaSucursal?->etiqueta ?? '',
            ], null, 'A' . $row);
            $row++;
        }

        $this->styleWorksheet($sheet, 'A1:L' . max(1, $row - 1));

        $prefix = $onlyWithEmpresa ? 'usuarios-empresas-' : 'usuarios-';

        return $this->downloadSpreadsheet($spreadsheet, $prefix . now()->format('Ymd-His') . '.xlsx');
    }

    public function empresasExcel()
    {
        return $this->excel(true);
    }

    public function pdf(?bool $forceOnlyWithEmpresa = null)
    {
        $onlyWithEmpresa = $forceOnlyWithEmpresa ?? request()->boolean('only_with_empresa');
        $empresaId = request()->filled('empresa_id') ? (int) request()->input('empresa_id') : null;
        $codigoCliente = trim((string) request()->input('codigo_cliente', ''));
        $users = $this->usersReportQuery($onlyWithEmpresa, $empresaId, $codigoCliente)->get();
        $generatedAt = now();

        $pdf = Pdf::loadView('user.pdf', [
            'users' => $users,
            'generatedAt' => $generatedAt,
            'onlyWithEmpresa' => $onlyWithEmpresa,
        ])->setPaper('A4', 'landscape');

        $prefix = $onlyWithEmpresa ? 'usuarios-empresas-' : 'usuarios-';

        return $pdf->stream($prefix . $generatedAt->format('Ymd-His') . '.pdf');
    }

    public function empresasPdf()
    {
        return $this->pdf(true);
    }

    public function templateExcel()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Usuarios');

        $headers = ['name *', 'alias *', 'email *', 'password *', 'ciudad *', 'ci', 'rol *', 'empresa_codigo *', 'sucursal_id', 'empresa_sucursal_id *'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([
            'Juan Perez',
            'juan.perez+lp',
            'juan.perez@correo.com',
            '12345678',
            'LA PAZ',
            '1234567',
            Role::query()->orderBy('name')->value('name') ?: 'admin',
            '',
            '',
        ], null, 'A2');

        $roles = Role::query()->orderBy('name')->pluck('name')->values();
        $rolesSheet = $spreadsheet->createSheet();
        $rolesSheet->setTitle('Roles');
        $rolesSheet->setCellValue('A1', 'roles_disponibles');
        foreach ($roles as $index => $roleName) {
            $rolesSheet->setCellValue('A' . ($index + 2), $roleName);
        }
        $rolesSheet->getColumnDimension('A')->setWidth(32);

        $empresas = Empresa::query()->orderBy('codigo_cliente')->get(['codigo_cliente', 'nombre', 'sigla']);
        $empresasSheet = $spreadsheet->createSheet();
        $empresasSheet->setTitle('Empresas');
        $empresasSheet->setCellValue('A1', 'codigo_empresa');
        $empresasSheet->setCellValue('B1', 'nombre');
        foreach ($empresas as $index => $empresa) {
            $excelRow = $index + 2;
            $empresasSheet->setCellValue('A' . $excelRow, $empresa->codigo_cliente);
            $empresasSheet->setCellValue('B' . $excelRow, trim($empresa->nombre . ' ' . ($empresa->sigla ? '(' . $empresa->sigla . ')' : '')));
        }
        $empresasSheet->getColumnDimension('A')->setWidth(24);
        $empresasSheet->getColumnDimension('B')->setWidth(56);

        $sucursalesSheet = $spreadsheet->createSheet();
        $sucursalesSheet->setTitle('Sucursales de empresa');
        $sucursalesSheet->fromArray(['empresa_sucursal_id', 'empresa_codigo', 'Empresa', 'Sucursal'], null, 'A1');
        $sucursales = EmpresaSucursal::query()->with('empresa')->orderBy('empresa_id')->orderBy('nombre')->get();
        foreach ($sucursales as $index => $sucursal) {
            $sucursalesSheet->fromArray([
                $sucursal->id,
                $sucursal->empresa->codigo_cliente,
                $sucursal->empresa->nombre,
                $sucursal->etiqueta,
            ], null, 'A' . ($index + 2));
        }
        $this->styleWorksheet($sucursalesSheet, 'A1:D' . ($sucursales->count() + 1));

        $roleCount = max(1, $roles->count());
        $roleRange = "'Roles'!\$A\$2:\$A\$" . ($roleCount + 1);
        for ($row = 2; $row <= 501; $row++) {
            $validation = $sheet->getCell('G' . $row)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(false);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Rol invalido');
            $validation->setError('Selecciona un rol de la lista.');
            $validation->setFormula1($roleRange);
        }

        $cityOptions = '"LA PAZ,COCHABAMBA,SANTA CRUZ,ORURO,POTOSI,TARIJA,SUCRE,TRINIDAD,COBIJA"';
        for ($row = 2; $row <= 501; $row++) {
            $validation = $sheet->getCell('E' . $row)->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(false);
            $validation->setShowDropDown(true);
            $validation->setFormula1($cityOptions);
        }

        if ($empresas->isNotEmpty()) {
            $empresaRange = "'Empresas'!\$A\$2:\$A\$" . ($empresas->count() + 1);
            for ($row = 2; $row <= 501; $row++) {
                $validation = $sheet->getCell('H' . $row)->getDataValidation();
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_STOP);
                $validation->setAllowBlank(false);
                $validation->setShowDropDown(true);
                $validation->setShowErrorMessage(true);
                $validation->setErrorTitle('Empresa invalida');
                $validation->setError('Selecciona un código de empresa de la lista.');
                $validation->setFormula1($empresaRange);
            }
        }

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Instrucciones');
        $instructions->fromArray([
            ['Instrucciones'],
            ['1) Completa una fila por usuario en la hoja Usuarios.'],
            ['2) Completa todos los campos marcados con *.'],
            ['3) Los campos rol, ciudad y empresa_codigo tienen combo box.'],
            ['4) password debe tener al menos 8 caracteres.'],
            ['5) alias y email no deben repetirse. Si ya existen, la fila no se importa.'],
            ['6) Usa la hoja Sucursales de empresa para elegir empresa_codigo y empresa_sucursal_id de la misma fila.'],
            ['7) sucursal_id es opcional y corresponde a la sucursal de Facturación.'],
        ], null, 'A1');
        $instructions->getColumnDimension('A')->setWidth(96);

        $spreadsheet->setActiveSheetIndex(0);
        $this->styleWorksheet($sheet, 'A1:J2');

        return $this->downloadSpreadsheet($spreadsheet, 'plantilla-importacion-usuarios.xlsx');
    }

    public function import(Request $request)
    {
        @set_time_limit(300);

        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        try {
            $spreadsheet = IOFactory::load($request->file('archivo')->getRealPath());
            $sheet = $spreadsheet->getSheetByName('Usuarios') ?: $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);
            $errors = [];
            $created = 0;
            $duplicateAliases = $this->collectDuplicateAliasesFromRows($rows);

            foreach ($rows as $rowNumber => $row) {
                if ($rowNumber === 1) {
                    continue;
                }

                $payload = [
                    'name' => trim((string) ($row['A'] ?? '')),
                    'alias' => strtolower(trim((string) ($row['B'] ?? ''))),
                    'email' => strtolower(trim((string) ($row['C'] ?? ''))),
                    'password' => trim((string) ($row['D'] ?? '')),
                    'ciudad' => trim((string) ($row['E'] ?? '')),
                    'ci' => trim((string) ($row['F'] ?? '')),
                    'rol' => trim((string) ($row['G'] ?? '')),
                    'empresa_codigo' => trim((string) ($row['H'] ?? '')),
                    'sucursal_id' => trim((string) ($row['I'] ?? '')),
                    'empresa_sucursal_id' => trim((string) ($row['J'] ?? '')),
                ];

                if (collect($payload)->filter(fn ($value) => $value !== '')->isEmpty()) {
                    continue;
                }

                if ($payload['alias'] !== '' && isset($duplicateAliases[$payload['alias']])) {
                    $errors[] = 'Fila ' . $rowNumber . ': el alias "' . $payload['alias'] . '" esta repetido en el Excel en las filas ' . implode(', ', $duplicateAliases[$payload['alias']]) . '. No se importo esta fila.';
                    continue;
                }

                $existingAliasUser = User::withTrashed()
                    ->whereRaw('LOWER(alias) = ?', [$payload['alias']])
                    ->first();

                if ($existingAliasUser) {
                    $errors[] = 'Fila ' . $rowNumber . ': el alias "' . $payload['alias'] . '" ya existe en el sistema. No se registro esta fila porque la importacion solo crea usuarios nuevos.';
                    continue;
                }

                $existingUser = User::withTrashed()->whereRaw('LOWER(email) = ?', [$payload['email']])->first();
                if ($existingUser) {
                    $errors[] = 'Fila ' . $rowNumber . ': el email "' . $payload['email'] . '" ya existe en el sistema. No se registro esta fila porque la importacion solo crea usuarios nuevos.';
                    continue;
                }

                $validator = Validator::make($payload, [
                    'name' => ['required', 'string', 'max:255'],
                    'alias' => ['required', 'string', 'max:255', Rule::unique('users', 'alias')],
                    'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
                    'password' => ['required', 'string', 'min:8'],
                    'ciudad' => ['required', 'string', 'max:255'],
                    'ci' => ['nullable', 'string', 'max:255'],
                    'rol' => ['required', 'string', 'exists:roles,name'],
                    'empresa_codigo' => ['bail', 'required', 'string', 'exists:empresa,codigo_cliente'],
                    'sucursal_id' => ['nullable', 'integer', 'exists:sucursales,id'],
                    'empresa_sucursal_id' => ['bail', 'required', 'integer', 'exists:empresa_sucursales,id'],
                ], $this->companyBranchMessages());

                if ($validator->fails()) {
                    $errors[] = 'Fila ' . $rowNumber . ': ' . $validator->errors()->first();
                    continue;
                }

                $sucursalEmpresa = EmpresaSucursal::query()
                    ->whereHas('empresa', fn ($query) => $query->where('codigo_cliente', $payload['empresa_codigo']))
                    ->find((int) $payload['empresa_sucursal_id']);
                if (! $sucursalEmpresa) {
                    $errors[] = 'Fila ' . $rowNumber . ': la sucursal debe pertenecer a la empresa indicada.';
                    continue;
                }

                $user = new User();
                $user->name = $payload['name'];
                $user->alias = $payload['alias'];
                $user->email = $payload['email'];
                $user->ciudad = strtoupper($payload['ciudad']);
                $user->regionales = [$user->ciudad];
                $user->ci = $payload['ci'] !== '' ? $payload['ci'] : null;
                $user->empresa_id = $sucursalEmpresa->empresa_id;
                $user->empresa_sucursal_id = $sucursalEmpresa->id;
                $user->sucursal_id = $payload['sucursal_id'] !== '' ? (int) $payload['sucursal_id'] : null;
                if ($payload['password'] !== '') {
                    $user->password = Hash::make($payload['password']);
                }
                $user->save();
                $user->syncRoles([$payload['rol']]);

                $created++;
            }

            $redirect = redirect()
                ->route('users.index')
                ->with('success', "Importacion finalizada. Creados: {$created}. No se actualizaron usuarios existentes.");

            if ($errors !== []) {
                $redirect->with('warning', 'Algunas filas no se importaron.')
                    ->with('import_errors', array_slice($errors, 0, 20));
            }

            return $redirect;
        } catch (\Throwable $exception) {
            Log::error('Error al importar usuarios desde Excel.', [
                'error' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('users.index')
                ->with('warning', 'No se pudo importar el Excel. Revisa el formato o intenta con menos filas.')
                ->with('import_errors', [$exception->getMessage()]);
        }
    }

    private function usersReportQuery(bool $onlyWithEmpresa = false, ?int $empresaId = null, string $codigoCliente = '')
    {
        return User::withTrashed()
            ->with(['empresa', 'empresaSucursal', 'sucursal', 'roles'])
            ->when($onlyWithEmpresa, fn ($query) => $query->whereNotNull('empresa_id'))
            ->when($codigoCliente !== '', function ($query) use ($codigoCliente) {
                $query->whereHas('empresa', function ($empresaQuery) use ($codigoCliente) {
                    $empresaQuery->whereRaw(
                        "UPPER(TRIM(COALESCE(codigo_cliente, ''))) = ?",
                        [mb_strtoupper($codigoCliente, 'UTF-8')]
                    );
                });
            })
            ->when($empresaId, fn ($query) => $query->where('empresa_id', $empresaId))
            ->orderBy('name');
    }

    private function companyBranchOptions(): array
    {
        return EmpresaSucursal::query()->orderBy('nombre')->get()
            ->groupBy('empresa_id')
            ->map(fn ($sucursales) => $sucursales->map(fn (EmpresaSucursal $sucursal) => [
                'id' => $sucursal->id,
                'label' => $sucursal->etiqueta,
            ])->values())
            ->toArray();
    }

    private function companyBranchMessages(): array
    {
        return [
            'empresa_id.required' => 'Selecciona una empresa.',
            'empresa_codigo.required' => 'Indica el código de la empresa.',
            'empresa_sucursal_id.required' => 'Selecciona una sucursal de la empresa.',
            'empresa_sucursal_id.exists' => 'La sucursal seleccionada debe pertenecer a la empresa elegida.',
        ];
    }

    private function styleWorksheet($sheet, string $range): void
    {
        $sheet->getStyle('1:1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF20539A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle($range)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFD9E2EF']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        foreach (range('A', $sheet->getHighestColumn()) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->setAutoFilter($range);
        $sheet->freezePane('A2');
    }

    private function downloadSpreadsheet(Spreadsheet $spreadsheet, string $filename)
    {
        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function normalizeAlias($alias): string
    {
        return strtolower(trim((string) $alias));
    }

    private function normalizeNullableUppercase($value): ?string
    {
        $normalized = trim((string) ($value ?? ''));
        $normalized = function_exists('mb_strtoupper') ? mb_strtoupper($normalized, 'UTF-8') : strtoupper($normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?: '';

        return $normalized !== '' ? $normalized : null;
    }

    private function ensureAliasIsAvailable(string $alias, ?int $ignoreUserId = null): void
    {
        if ($this->isAliasAvailable($alias, $ignoreUserId)) {
            return;
        }

        throw ValidationException::withMessages([
            'alias' => 'El alias ya esta registrado. Debe ser unico.',
        ]);
    }

    private function isAliasAvailable(string $alias, ?int $ignoreUserId = null): bool
    {
        if ($alias === '') {
            return true;
        }

        return ! User::withTrashed()
            ->when($ignoreUserId, fn ($query) => $query->where('id', '!=', $ignoreUserId))
            ->whereRaw('LOWER(alias) = ?', [$alias])
            ->exists();
    }

    private function collectDuplicateAliasesFromRows(array $rows): array
    {
        $aliasRows = [];

        foreach ($rows as $rowNumber => $row) {
            if ($rowNumber === 1) {
                continue;
            }

            $alias = $this->normalizeAlias($row['B'] ?? '');

            if ($alias === '') {
                continue;
            }

            $payload = [
                trim((string) ($row['A'] ?? '')),
                $alias,
                strtolower(trim((string) ($row['C'] ?? ''))),
                trim((string) ($row['D'] ?? '')),
                trim((string) ($row['E'] ?? '')),
                trim((string) ($row['F'] ?? '')),
                trim((string) ($row['G'] ?? '')),
                trim((string) ($row['H'] ?? '')),
                trim((string) ($row['I'] ?? '')),
            ];

            if (collect($payload)->filter(fn ($value) => $value !== '')->isEmpty()) {
                continue;
            }

            $aliasRows[$alias][] = $rowNumber;
        }

        return collect($aliasRows)
            ->filter(fn (array $rowNumbers) => count($rowNumbers) > 1)
            ->all();
    }
}

