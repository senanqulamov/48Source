<?php

namespace App\Http\Middleware;

use App\Support\Translator;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Shows pages and Livewire updates in the language the user chose. */
class TranslatePage
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $locale = app()->getLocale();

        if (! Translator::has($locale) || $response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return $response;
        }

        $type = (string) $response->headers->get('Content-Type');

        if ($response instanceof JsonResponse || str_contains($type, 'json')) {
            $data = json_decode((string) $response->getContent(), true);
            if (is_array($data) && isset($data['components']) && is_array($data['components'])) {
                foreach ($data['components'] as $i => $component) {
                    if (isset($component['effects']['html']) && is_string($component['effects']['html'])) {
                        $data['components'][$i]['effects']['html'] = Translator::html($component['effects']['html'], $locale);
                    }
                }
                $response->setContent((string) json_encode($data));
            }

            return $response;
        }

        if (str_contains($type, 'text/html') || ($type === '' && is_string($response->getContent()) && str_starts_with(ltrim((string) $response->getContent()), '<!'))) {
            $response->setContent(Translator::html((string) $response->getContent(), $locale));
        }

        return $response;
    }
}
