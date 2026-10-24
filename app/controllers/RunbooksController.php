<?php

declare(strict_types=1);

final class RunbooksController
{
    public function index(): void
    {
        Auth::requireLogin();
        $org = Auth::user()['organizacion_id'];
        $runbooks = RunbookModel::all($org);
        require dirname(__DIR__, 2) . '/views/modulos/runbooks/index.php';
    }
}
