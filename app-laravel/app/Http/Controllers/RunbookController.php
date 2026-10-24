<?php

namespace App\Http\Controllers;

use App\Models\Runbook;

class RunbookController extends Controller
{
    public function index()
    {
        $runbooks = Runbook::with('activo')
            ->orderByDesc('validado')->orderByDesc('veces_exitoso')->orderByDesc('created_at')
            ->get();

        return view('modulos.runbooks.index', compact('runbooks'));
    }
}
