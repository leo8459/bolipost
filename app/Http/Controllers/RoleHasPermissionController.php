<?php

namespace App\Http\Controllers;

class RoleHasPermissionController extends Controller
{
    public function index()
    {
        return redirect()->route('roles.index')
            ->with('warning', 'La gestion de accesos ahora se realiza desde Roles.');
    }

    public function create()
    {
        return redirect()->route('roles.create');
    }

    public function store()
    {
        return redirect()->route('roles.index');
    }

    public function show($id)
    {
        return redirect()->route('roles.index');
    }

    public function edit($id)
    {
        return redirect()->route('roles.index');
    }

    public function update($id)
    {
        return redirect()->route('roles.index');
    }

    public function destroy()
    {
        return redirect()->route('roles.index');
    }
}
