<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class InjectPwaScript
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $contentType = $response->headers->get('Content-Type') ?? '';

        if (str_contains($contentType, 'text/html')) {
            $content = $response->getContent();

            // inject manifest link into head if not present
            if (! str_contains($content, 'manifest.webmanifest')) {
                $manifestLink = "<link rel=\"manifest\" href=\"/manifest.webmanifest\">\n";
                $content = preg_replace('/<\/head>/i', $manifestLink.'</head>', $content, 1);
            }

            // inject theme-color meta tag
            if (! str_contains($content, 'name="theme-color"')) {
                $meta = "<meta name=\"theme-color\" content=\"#1b1b18\">\n";
                $content = preg_replace('/<\/head>/i', $meta.'</head>', $content, 1);
            }

            // inject apple touch icon
            if (! str_contains($content, 'apple-touch-icon')) {
                $apple = "<link rel=\"apple-touch-icon\" sizes=\"180x180\" href=\"/build/icons/apple-touch-icon.png\">\n";
                $content = preg_replace('/<\/head>/i', $apple.'</head>', $content, 1);
            }

            // inject mask-icon and Windows tile (msapplication)
            if (! str_contains($content, 'mask-icon')) {
                $mask = "<link rel=\"mask-icon\" href=\"/build/icons/mask-icon.svg\" color=\"#1b1b18\">\n";
                $content = preg_replace('/<\/head>/i', $mask.'</head>', $content, 1);
            }

            if (! str_contains($content, 'msapplication-TileImage')) {
                $ms = "<meta name=\"msapplication-TileColor\" content=\"#1b1b18\">\n";
                $ms .= "<meta name=\"msapplication-TileImage\" content=\"/build/icons/icon-512.png\">\n";
                $content = preg_replace('/<\/head>/i', $ms.'</head>', $content, 1);
            }

            // inject service worker registration script before body close
            if (! str_contains($content, 'sw-register.js')) {
                $scriptTag = "<script src=\"/sw-register.js\"></script>\n";
                $content = preg_replace('/<\/body>/i', $scriptTag.'</body>', $content, 1);
            }

            $response->setContent($content);
        }

        return $response;
    }
}
