<?php

namespace Tests\Feature\Validation;

use App\Support\Validation\CostRule;
use App\Support\Validation\MoneyRule;
use App\Support\Validation\QuantityRule;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * The canonical-number rules at the HTTP boundary, through the real kernel
 * and the application's global middleware (ADR-006 item 2, AC-003-02;
 * TASK-003 adversarial findings A-01 and A-09). A number that the global
 * trimming of input would change is refused with the rule's own message,
 * never trimmed into a canonical one, whatever the field is called and
 * whether the request is JSON, urlencoded or multipart; a text field keeps its
 * trimming. Two invalid fields give a validation response, not a missing
 * translation key. The route exists only in this test; no route of
 * ROUTE_CONTRACTS is added.
 */
final class CanonicalNumberInputTest extends TestCase
{
    private const string URI = '/__tests/numbers';

    protected function setUp(): void
    {
        parent::setUp();

        Route::post(self::URI, fn (Request $request) => response()->json($request->validate([
            'money' => ['sometimes', new MoneyRule],
            'harga_jual' => ['sometimes', new MoneyRule],
            'quantity' => ['sometimes', new QuantityRule(3)],
            'lines.*.qty' => ['sometimes', new QuantityRule(3)],
            'cost' => ['sometimes', new CostRule(self::text('ux.validation.money'), self::text('ux.validation.money_max'))],
            'note' => ['sometimes', 'string'],
        ])));
    }

    /**
     * Every padding that Laravel's TrimStrings removes (Str::trim), around a
     * canonical value of each kind.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function paddedNumbers(): array
    {
        $cases = [];

        foreach (['money' => '75000', 'quantity' => '1.5', 'cost' => '12000.5'] as $field => $canonical) {
            foreach ([' %s', '%s ', "\t%s", "%s\n", "%s\r\n", "\u{00A0}%s", "%s\u{200B}", "\u{FEFF}%s", "%s\u{3000}", "%s\0", ' %s '] as $pattern) {
                $value = sprintf($pattern, $canonical);
                $cases[$field.' '.json_encode($value)] = [$field, $value, self::messageKeyOf($field)];
            }
        }

        return $cases;
    }

    #[DataProvider('paddedNumbers')]
    public function test_a_padded_number_in_json_is_refused_with_the_rules_message(string $field, string $value, string $key): void
    {
        $this->assertRefusedWith($this->postJson(self::URI, [$field => $value]), $field, $key);
    }

    #[DataProvider('paddedNumbers')]
    public function test_a_padded_number_in_a_form_is_refused_with_the_rules_message(string $field, string $value, string $key): void
    {
        $this->assertRefusedWith($this->post(self::URI, [$field => $value], ['Accept' => 'application/json']), $field, $key);
    }

    public function test_a_padded_number_in_a_multipart_request_is_refused(): void
    {
        $response = $this->call('POST', self::URI, ['money' => ' 75000 '], [], ['receipt' => UploadedFile::fake()->create('nota.txt', 1)], [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'multipart/form-data; boundary=sample',
        ]);

        $this->assertRefusedWith($response, 'money', 'ux.validation.money');
    }

    public function test_the_refusal_follows_the_rule_whatever_the_field_is_called(): void
    {
        $this->assertRefusedWith($this->postJson(self::URI, ['harga_jual' => '75000 ']), 'harga_jual', 'ux.validation.money');
        $this->assertRefusedWith($this->postJson(self::URI, ['lines' => [['qty' => '1'], ['qty' => "\u{00A0}2.5"]]]), 'lines.1.qty', 'ux.validation.quantity_positive');

        $this->postJson(self::URI, ['lines' => [['qty' => '1'], ['qty' => '2.5']]])->assertOk();
    }

    public function test_canonical_numbers_pass_and_a_text_field_keeps_its_trimming(): void
    {
        $this->postJson(self::URI, ['money' => '75000', 'quantity' => '1.5', 'cost' => '12000.5', 'note' => '  Catatan  '])
            ->assertOk()
            ->assertExactJson(['money' => '75000', 'quantity' => '1.5', 'cost' => '12000.5', 'note' => 'Catatan']);
    }

    public function test_json_numbers_and_other_types_are_refused(): void
    {
        foreach ([['money' => 75000], ['money' => 75000.0], ['quantity' => 1.5], ['cost' => 0.5], ['money' => [75000]], ['money' => true]] as $payload) {
            $this->assertSame(422, $this->postJson(self::URI, $payload)->getStatusCode(), json_encode($payload).' was accepted');
        }
    }

    public function test_two_invalid_fields_give_a_validation_response_with_both_messages(): void
    {
        $response = $this->postJson(self::URI, ['money' => '1.5', 'quantity' => '-1']);

        $this->assertSame(422, $response->getStatusCode(), 'status '.$response->getStatusCode().': '.mb_substr((string) $response->getContent(), 0, 300));
        $response->assertJsonValidationErrors([
            'money' => self::text('ux.validation.money'),
            'quantity' => self::text('ux.validation.quantity_positive'),
        ]);
    }

    /**
     * @param  TestResponse<Response>  $response
     */
    private function assertRefusedWith(TestResponse $response, string $field, string $key): void
    {
        $this->assertSame(422, $response->getStatusCode(), sprintf('%s was accepted: %s', $field, mb_substr((string) $response->getContent(), 0, 300)));
        $errors = $response->json('errors');
        $this->assertIsArray($errors);
        $this->assertSame([self::text($key)], $errors[$field] ?? null);
    }

    private static function messageKeyOf(string $field): string
    {
        return match ($field) {
            'money', 'cost' => 'ux.validation.money',
            default => 'ux.validation.quantity_positive',
        };
    }

    private static function text(string $key): string
    {
        $text = __($key);

        return is_string($text) ? $text : '';
    }
}
