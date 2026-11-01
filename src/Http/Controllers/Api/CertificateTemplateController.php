<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelCourses\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use FancyTemplate\Template;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

        $data = $request->validate([
            'slug'             => ($ignoreId ? 'sometimes|' : 'required|') . $slugRule,
            'name'             => ($ignoreId ? 'sometimes|' : 'required|') . 'string|max:255',
            'description'      => 'nullable|string',
            'blade_view'       => 'nullable|string|max:255',
            'html'             => 'nullable|string',
            'css'              => 'nullable|string',
            'is_default'       => 'nullable|boolean',
            'variables_schema' => 'nullable|array',
            'document'         => 'nullable|array',
        ]);

        if (isset($data['document'])) {
            $this->assertDrawable($data['document']);
        }

        return $data;
    }

    /**
     * A template is checked when it is SAVED, so a broken one fails here with
     * its paths rather than when a learner downloads a certificate.
     *
     * @param  array<string,mixed>  $document
     */
    private function assertDrawable(array $document): void
    {
        if (($document['writer'] ?? null) !== 'fine-print') {
            throw ValidationException::withMessages([
                'document' => 'The document must be a fancy-template envelope for the fine-print writer.',
            ]);
        }

        $inspected = Template::inspect($document);
        if (! $inspected['ok']) {
            throw ValidationException::withMessages([
                'document' => array_map(
                    static fn (array $issue): string => trim(
                        "{$issue['code']} at /document{$issue['path']} " . ($issue['variable'] ?? ''),
                    ),
                    $inspected['issues'],
                ),
            ]);
        }
    }
}
