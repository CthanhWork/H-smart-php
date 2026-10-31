<?php

namespace App\Modules\Assistant\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class AiServiceClient
{
    /** @return array{answer: string, citations: array<int, array{source_id: string, title: string, section: string}>} */
    public function answer(string $question): array
    {
        $response = Http::acceptJson()
            ->asJson()
            ->timeout((int) config('services.ai.timeout', 25))
            ->post(rtrim((string) config('services.ai.url'), '/').'/v1/chat', [
                'schema_version' => 1,
                'request_id' => (string) Str::uuid(),
                'question' => $question,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('AI service unavailable');
        }

        $data = $response->json();
        if (! is_array($data) || ($data['schema_version'] ?? null) !== 1
            || ! is_string($data['answer'] ?? null) || mb_strlen($data['answer']) > 6000
            || ! is_array($data['citations'] ?? null)) {
            throw new RuntimeException('AI service returned an invalid response');
        }

        $citations = [];
        foreach (array_slice($data['citations'], 0, 5) as $citation) {
            if (! is_array($citation) || ! is_string($citation['source_id'] ?? null)
                || ! is_string($citation['title'] ?? null) || ! is_string($citation['section'] ?? null)) {
                throw new RuntimeException('AI service returned an invalid citation');
            }
            $citations[] = [
                'source_id' => mb_substr($citation['source_id'], 0, 100),
                'title' => mb_substr($citation['title'], 0, 160),
                'section' => mb_substr($citation['section'], 0, 160),
            ];
        }

        return ['answer' => trim($data['answer']), 'citations' => $citations];
    }

    /** @return array{label: string, translated_label: string, confidence: float, num_detections: int, detections: array<int, array{label: string, translated_label: string, class_id: int, score: float, bbox: array<int, float>}>} */
    public function detectImage(UploadedFile $image): array
    {
        $extension = $image->guessExtension() ?: 'jpg';
        $response = Http::acceptJson()
            ->timeout((int) config('services.ai.vision_timeout', 40))
            ->attach('file', $image->getContent(), 'product-image.'.$extension, ['Content-Type' => $image->getMimeType()])
            ->post(rtrim((string) config('services.ai.url'), '/').'/api/v1/predict');

        if (! $response->successful()) {
            throw new RuntimeException('AI vision service unavailable');
        }

        $data = $response->json();
        if (! is_array($data) || ! is_string($data['label'] ?? null)
            || ! is_string($data['translated_label'] ?? null)
            || ! is_numeric($data['confidence'] ?? null)
            || ! is_numeric($data['num_detections'] ?? null)
            || ! is_array($data['detections'] ?? null) || count($data['detections']) > 100) {
            throw new RuntimeException('AI vision service returned an invalid response');
        }

        $detections = [];
        foreach ($data['detections'] as $detection) {
            if (! is_array($detection) || ! is_string($detection['label'] ?? null)
                || ! is_string($detection['translated_label'] ?? null)
                || ! is_numeric($detection['class_id'] ?? null)
                || ! is_numeric($detection['score'] ?? null)
                || ! is_array($detection['bbox'] ?? null)
                || ! in_array(count($detection['bbox']), [0, 4], true)) {
                throw new RuntimeException('AI vision service returned an invalid detection');
            }
            $detections[] = [
                'label' => mb_substr($detection['label'], 0, 100),
                'translated_label' => mb_substr($detection['translated_label'], 0, 100),
                'class_id' => (int) $detection['class_id'],
                'score' => (float) $detection['score'],
                'bbox' => array_map('floatval', $detection['bbox']),
            ];
        }

        return [
            'label' => mb_substr($data['label'], 0, 100),
            'translated_label' => mb_substr($data['translated_label'], 0, 100),
            'confidence' => (float) $data['confidence'],
            'num_detections' => (int) $data['num_detections'],
            'detections' => $detections,
        ];
    }

    /** @return array{description: string, vision: array<string, mixed>|null} */
    public function generateListingDescription(array $input, ?UploadedFile $image): array
    {
        $request = Http::acceptJson()
            ->asMultipart()
            ->timeout((int) config('services.ai.description_timeout', 65));

        if ($image) {
            $extension = $image->guessExtension() ?: 'jpg';
            $request = $request->attach('file', $image->getContent(), 'product-image.'.$extension, ['Content-Type' => $image->getMimeType()]);
        }

        $response = $request->post(rtrim((string) config('services.ai.url'), '/').'/v1/listing-description', [
            'title' => $input['title'],
            'category' => $input['category'],
            'condition' => $input['condition'],
            'price' => (string) ($input['price'] ?? ''),
            'seller_notes' => (string) ($input['seller_notes'] ?? ''),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('AI listing description service unavailable');
        }

        $data = $response->json();
        if (! is_array($data) || ! is_string($data['description'] ?? null)
            || trim($data['description']) === '' || mb_strlen($data['description']) > 2500) {
            throw new RuntimeException('AI service returned an invalid description');
        }

        $vision = $data['vision'] ?? null;
        if ($vision !== null) {
            if (! is_array($vision) || ! is_string($vision['label'] ?? null)
                || ! is_string($vision['translated_label'] ?? null)
                || ! is_numeric($vision['confidence'] ?? null)
                || ! is_numeric($vision['num_detections'] ?? null)
                || ! is_array($vision['detections'] ?? null) || count($vision['detections']) > 100) {
                throw new RuntimeException('AI service returned invalid vision results');
            }
            foreach ($vision['detections'] as $detection) {
                if (! is_array($detection) || ! is_string($detection['translated_label'] ?? null)
                    || ! is_numeric($detection['score'] ?? null)) {
                    throw new RuntimeException('AI service returned an invalid detection');
                }
            }
        }

        return ['description' => trim($data['description']), 'vision' => $vision];
    }
}
