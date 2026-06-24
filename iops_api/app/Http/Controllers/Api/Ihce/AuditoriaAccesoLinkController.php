<?php

namespace App\Http\Controllers\Api\Ihce;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ihce\StoreAuditoriaAccesoLinkRequest;
use App\Services\Ihce\AuditoriaAccesoLinkService;
use Illuminate\Http\JsonResponse;

class AuditoriaAccesoLinkController extends Controller
{
    /**
     * @var AuditoriaAccesoLinkService
     */
    protected $auditoriaService;

    /**
     * AuditoriaAccesoLinkController constructor.
     *
     * @param AuditoriaAccesoLinkService $auditoriaService
     */
    public function __construct(AuditoriaAccesoLinkService $auditoriaService)
    {
        $this->auditoriaService = $auditoriaService;
    }

    /**
     * Store a new audit link record.
     *
     * @param StoreAuditoriaAccesoLinkRequest $request
     * @return JsonResponse
     */
    public function store(StoreAuditoriaAccesoLinkRequest $request): JsonResponse
    {
        $result = $this->auditoriaService->storeAuditRecord($request->validated());

        // We return a 200 OK even when it exists (status: false), as per the requirements,
        // it just needs to indicate status and message.
        return response()->json($result);
    }
}
