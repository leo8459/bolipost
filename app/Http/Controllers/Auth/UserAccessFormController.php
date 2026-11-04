<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;

class UserAccessFormController extends Controller
{
    public function creation()
    {
        return response()->download(
            public_path('formatos/solicitud-creacion-usuario.docx'),
            'formulario-solicitud-creacion-de-usuario.docx'
        );
    }

    public function changes()
    {
        return response()->download(
            public_path('formatos/solicitud-baja-edicion-usuario.docx'),
            'formulario-solicitud-baja-o-edicion-de-usuario.docx'
        );
    }

    public function systemChanges()
    {
        return response()->download(
            public_path('formatos/solicitud-cambio-sistema.docx'),
            'formulario-solicitud-cambio-del-sistema-siop.docx'
        );
    }
}
