<?php

namespace Tests\Unit\Http;

use App\Http\Controllers\Testing\SpecimenController;
use Illuminate\Routing\RouteCollection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The component specimen of the design system (TASK-005 scope item 8) exists
 * only in the local and testing environments, never in staging or production,
 * and answers with the content security policy of SEC-HDR-03, whose nonce the
 * generated tags carry.
 */
final class SpecimenTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function environments(): array
    {
        return [
            'local' => ['local', true],
            'testing' => ['testing', true],
            'staging' => ['staging', false],
            'production' => ['production', false],
            'empty' => ['', false],
            'another spelling' => ['Local', false],
        ];
    }

    #[DataProvider('environments')]
    public function test_it_is_enabled_only_in_the_local_and_testing_environments(string $environment, bool $enabled): void
    {
        $this->assertSame($enabled, SpecimenController::enabledIn($environment));
    }

    public function test_it_answers_in_the_testing_environment_with_the_policy_and_its_nonce(): void
    {
        $this->withoutVite();

        $response = $this->get('/testing/specimen');

        $response->assertOk();
        $policy = (string) $response->headers->get('Content-Security-Policy');
        $this->assertSame(1, preg_match("/'nonce-([A-Za-z0-9]{40})'/", $policy, $match));
        $nonce = $match[1] ?? '';
        $this->assertStringContainsString("script-src 'self' 'nonce-{$nonce}'", $policy);
        $this->assertStringContainsString("style-src 'self' 'nonce-{$nonce}'", $policy);
        $response->assertSee('<meta name="csp-nonce" nonce="'.$nonce.'">', false);
        $page = $response->viewData('page');
        $this->assertIsArray($page);
        $this->assertSame('Testing/Specimen', $page['component'] ?? null);
    }

    #[DataProvider('environments')]
    public function test_the_route_table_holds_it_only_where_it_is_enabled(string $environment, bool $enabled): void
    {
        $this->app['env'] = $environment;
        $router = $this->app->make('router');
        $router->setRoutes(new RouteCollection);

        require base_path('routes/web.php');
        $router->getRoutes()->refreshNameLookups();

        $this->assertSame($enabled, $router->getRoutes()->getByName('testing.specimen') !== null);
    }
}
