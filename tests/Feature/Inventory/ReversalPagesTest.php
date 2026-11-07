<?php

namespace Tests\Feature\Inventory;

use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Harness\Channels\ChannelInspector;
use Tests\Harness\Commands\CommandRequests;
use Tests\Harness\DatabaseTestCase;
use Tests\Harness\Fixtures\TokoContoh;

/**
 * RP-411 and the entries of RP-403, RP-406 and RP-409 (AC-026-05; ROUTE_CONTRACTS RP-411, RP-403,
 * RP-406, RP-409; NAVIGATION_CONTRACTS UXP-411; COPY_DECK sections 9.4 and 9.12; BR-INV-34 to
 * BR-INV-36; CAP-14, CAP-17; EXECUTION_CONTEXT section 10, a route and a read; suites AUTH01, AUTH02,
 * INV03; TASK-026 scope item 4), as L4 tests of the pages' answers: RP-411's props against its
 * allowlist, the Owner's alone, every other kind of account refused; the exact inverse and the stock
 * now and after, never a cost; a movement that no reversal may cancel NOT_FOUND; its parameter an
 * allowlist; the reversal links of UXP-403 for every holder of CAP-14; and the Owner's alone of the
 * reversed lines and the onboarding freeze.
 */
final class ReversalPagesTest extends DatabaseTestCase
{
    use ChannelInspector;
    use CommandRequests;

    private int $pen = 0;

    private int $document = 0;

    private int $receipt = 0;

    private int $issueRecord = 0;

    private int $issue = 0;

    protected function setUp(): void
    {
        parent::setUp();

        TokoContoh::shop();
        $this->setUpCommands();
        $this->pen = TokoContoh::product('PEN-01', ['name' => 'Pulpen Hitam', 'categoryId' => TokoContoh::category()]);
        $this->document = TokoContoh::stock($this->pen, '10', '100000');
        $this->receipt = TokoContoh::movementOf($this->document);
        $this->issueRecord = TokoContoh::issue([['productId' => $this->pen, 'quantity' => '3']]);
        $this->issue = TokoContoh::movementOf($this->issueRecord, 'MANUAL_ISSUE');
    }

    public function test_only_the_owner_opens_rp_411_with_only_its_allowlisted_props(): void
    {
        $secrets = array_values(array_map(static fn (array $one): string => $one['password'], $this->people));
        $path = '/stock/movements/'.$this->receipt.'/reverse?from_page=receiving';
        $this->as('Hendra');

        $response = $this->get($path)->assertOk();
        $this->assertPageChannel($response, 'owner', $secrets);
        $props = $this->propsOf($response);
        $this->assertSame('inventory/Reversal', $this->inertiaPage($response)['component'] ?? null);
        // UXI-277: the source, its exact inverse, the stock now and after; no cost (BR-INV-34).
        $this->assertSame([
            'movementId' => $this->receipt, 'type' => 'RECEIPT', 'quantity' => '10', 'inverse' => '-10', 'baseUnit' => 'pcs', 'quantityPrecision' => 0,
            'documentId' => $this->document, 'stockNow' => '7', 'stockAfter' => '-3', 'reversed' => false,
        ], array_diff_key((array) ($props['source'] ?? []), ['recordedAt' => true, 'product' => true]));
        $this->assertSame([false, 'receiving'], [$props['postingFrozen'] ?? null, $props['opener'] ?? null]);

        // THR-19: every other role and the combined account are refused.
        foreach (['Agus', 'Rina', 'Dewi'] as $person) {
            $this->as($person);
            $page = $this->get($path)->assertStatus(403)->viewData('page');
            $this->assertIsArray($page);
            $this->assertSame('shell/Error', $page['component'] ?? null, $person);
        }

        $this->browse(null);
        $this->get($path)->assertRedirect('/login');
    }

    public function test_a_movement_that_no_reversal_may_cancel_is_not_found_and_the_parameter_is_an_allowlist(): void
    {
        // BR-INV-36: an ADJUSTMENT and a REVERSAL are no source; TR-ERR-03: a missing movement.
        TokoContoh::adjust([['productId' => $this->pen, 'direction' => 'DECREASE', 'quantity' => '1']]);
        $adjustment = self::number($this->observer()->table('stock_movements')->where('movement_type', 'ADJUSTMENT')->value('id'));
        TokoContoh::reverse($this->issue);
        $reversal = self::number($this->observer()->table('stock_movements')->where('movement_type', 'REVERSAL')->value('id'));
        $this->as('Hendra');

        foreach ([$adjustment, $reversal, 999999, 'x'] as $movement) {
            $this->getJson('/stock/movements/'.$movement.'/reverse')->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        }

        foreach (['from_page=worklist', 'from_page=', 'product=1', 'reason=x'] as $query) {
            $this->getJson('/stock/movements/'.$this->receipt.'/reverse?'.$query)->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');
        }

        // A reversed source still opens, and says that it is reversed (UXS-13).
        $this->assertTrue((bool) ($this->propsOf($this->get('/stock/movements/'.$this->issue.'/reverse')->assertOk())['source']['reversed'] ?? false));
    }

    public function test_the_entries_and_the_reversal_links_of_the_movement_receiving_and_issue_pages(): void
    {
        // UXI-227, UXI-250, UXI-266: the Owner's alone see whether each line is reversed and whether
        // the freeze is on; every holder of CAP-14 sees the reversal links of UXP-403 (COPY_DECK 9.4).
        TokoContoh::reverse($this->issue);
        $reversal = self::number($this->observer()->table('stock_movements')->where('movement_type', 'REVERSAL')->value('id'));
        $secrets = array_values(array_map(static fn (array $one): string => $one['password'], $this->people));

        foreach (['Hendra' => 'owner', 'Agus' => 'ops', 'Dewi' => 'combined'] as $person => $kind) {
            $this->as($person);
            $issue = $this->get('/stock/issues/'.$this->issueRecord)->assertOk();
            $this->assertPageChannel($issue, $kind, $secrets);
            $receiving = $this->get('/stock/receivings/'.$this->document)->assertOk();
            $this->assertPageChannel($receiving, $kind, $secrets);
            $source = $this->get('/stock/movements/'.$this->issue)->assertOk();
            $this->assertPageChannel($source, $kind, $secrets);
            $this->assertPageChannel($this->get('/stock/movements/'.$reversal)->assertOk(), $kind, $secrets);

            $owner = $kind === 'owner';
            $this->assertSame($owner ? [true] : [null], array_map(static fn (mixed $line): mixed => is_array($line) ? $line['reversed'] ?? null : null, (array) ($this->propsOf($issue)['lines'] ?? [])), $person);
            $this->assertSame($owner ? [false] : [null], array_map(static fn (mixed $line): mixed => is_array($line) ? $line['reversed'] ?? null : null, (array) ($this->propsOf($receiving)['lines'] ?? [])), $person);
            $this->assertSame($owner, array_key_exists('postingFrozen', $this->propsOf($source)), $person);
            $this->assertSame($reversal, ((array) ($this->propsOf($source)['movement'] ?? []))['reversedBy']['id'] ?? null, $person);
        }

        $this->as('Hendra');
        $links = (array) ($this->propsOf($this->get('/stock/movements/'.$reversal))['movement'] ?? []);
        $this->assertSame([$this->issue, null, null], [$links['reverses']['id'] ?? null, $links['reversedBy'] ?? null, $links['source'] ?? null]);
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return array<array-key, mixed>
     */
    private function propsOf(TestResponse $response): array
    {
        return (array) ($this->inertiaPage($response)['props'] ?? []);
    }
}
