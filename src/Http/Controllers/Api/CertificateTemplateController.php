<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use ParticleAcademy\LaravelCourses\Http\Resources\CertificateTemplateResource;
use ParticleAcademy\LaravelCourses\Models\CertificateTemplate;

class CertificateTemplateController extends Controller
{
    public function index(): mixed
    {
        return CertificateTemplateResource::collection(CertificateTemplate::query()->orderBy('id')->get());
    }

    public function show(CertificateTemplate $template): CertificateTemplateResource
    {
        return new CertificateTemplateResource($template);
    }

    public function store(Request $request): CertificateTemplateResource
    {
        $data = $this->validatePayload($request);

        return DB::transaction(function () use ($data): CertificateTemplateResource {
            if ($data['is_default'] ?? false) {
                CertificateTemplate::query()->update(['is_default' => false]);
            }

            return new CertificateTemplateResource(CertificateTemplate::create($data));
        });
    }

    public function update(Request $request, CertificateTemplate $template): CertificateTemplateResource
    {
        $data = $this->validatePayload($request, $template->id);

        return DB::transaction(function () use ($data, $template): CertificateTemplateResource {
            if (($data['is_default'] ?? false) === true) {
                CertificateTemplate::query()->where('id', '!=', $template->id)->update(['is_default' => false]);
            }

            $template->update($data);

            return new CertificateTemplateResource($template->fresh());
        });
    }

    public function destroy(CertificateTemplate $template): JsonResponse
    {
        $template->delete();

        return response()->json(null, 204);
    }

    /** @return array<string,mixed> */
    private function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        $slugRule = 'string|max:255|unique:certificate_templates,slug' . ($ignoreId ? ",{$ignoreId}" : '');

        return $request->validate([
            'slug'             => ($ignoreId ? 'sometimes|' : 'required|') . $slugRule,
            'name'             => ($ignoreId ? 'sometimes|' : 'required|') . 'string|max:255',
            'description'      => 'nullable|string',
            'blade_view'       => 'nullable|string|max:255',
            'html'             => 'nullable|string',
            'css'              => 'nullable|string',
            'is_default'       => 'nullable|boolean',
            'variables_schema' => 'nullable|array',
        ]);
    }
}
