<?php

namespace Tests\Feature;

use Tests\TestCase;

class SitemapTest extends TestCase
{
    public function test_sitemap_returns_valid_xml(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee('<?xml version="1.0" encoding="UTF-8"?>', false);
        $response->assertSee('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', false);
        $response->assertSee('<loc>'.rtrim(url('/'), '/').'/</loc>', false);
        $response->assertSee('<lastmod>', false);
    }

    public function test_robots_txt_points_to_absolute_sitemap_and_blocks_app_routes(): void
    {
        $base = rtrim(url('/'), '/');

        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->assertSee('Allow: /', false);
        $response->assertSee('Disallow: /dashboard', false);
        $response->assertSee('Disallow: /admin', false);
        $response->assertSee('Sitemap: '.$base.'/sitemap.xml', false);
    }
}
