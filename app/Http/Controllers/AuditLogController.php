<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexAuditLogRequest;
use App\Models\AuditLog;
use App\Services\AuditLogWorkspaceService;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(
        IndexAuditLogRequest $request,
        AuditLogWorkspaceService $workspace,
    ): View {
        return view(
            'audit-logs.index',
            $workspace->indexData($request->filters()),
        );
    }

    public function show(
        AuditLog $auditLog,
        AuditLogWorkspaceService $workspace,
    ): View {
        return view(
            'audit-logs.show',
            $workspace->detailData($auditLog),
        );
    }
}
