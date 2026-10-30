<?php

namespace App\Domain\Configuration;

use InvalidArgumentException;

/**
 * The fields of a BusinessProfile revision (DOMAIN_MODEL BR-CFG-01; SCHEMA
 * section 5.2; INTERACTION_CONTRACTS RC-32), trimmed and validated by the
 * caller (TR-VAL-05; TECHNICAL_RULES section 11.3): a null optional field is
 * its absence, never an empty text. The lengths are checked again here and by
 * the database's checks.
 */
final readonly class ProfileFields
{
    /** TECHNICAL_RULES section 11.3, in characters. */
    public const array LENGTHS = [
        'shopName' => 100,
        'address' => 500,
        'contact' => 200,
        'email' => 254,
        'documentHeader' => 500,
        'documentFooter' => 500,
        'documentIdentity' => 100,
    ];

    public function __construct(
        public string $shopName,
        public ?string $address = null,
        public ?string $contact = null,
        public ?string $email = null,
        public ?string $documentHeader = null,
        public ?string $documentFooter = null,
        public ?string $documentIdentity = null,
        public ?string $accentToken = null,
    ) {
        foreach ($this->texts() as $name => $value) {
            if ($value !== null && ($value === '' || mb_strlen($value) > self::LENGTHS[$name])) {
                throw new InvalidArgumentException(sprintf('The BusinessProfile field %s is empty or too long (TECHNICAL_RULES section 11.3).', $name));
            }
        }

        if ($accentToken !== null && ! in_array($accentToken, Accents::TOKENS, true)) {
            throw new InvalidArgumentException('The accent is one of DESIGN_SYSTEM section 3.3.');
        }
    }

    /**
     * The text fields by name.
     *
     * @return array<string, string|null>
     */
    public function texts(): array
    {
        return [
            'shopName' => $this->shopName,
            'address' => $this->address,
            'contact' => $this->contact,
            'email' => $this->email,
            'documentHeader' => $this->documentHeader,
            'documentFooter' => $this->documentFooter,
            'documentIdentity' => $this->documentIdentity,
        ];
    }

    /**
     * The names of the fields whose values differ from the other's, the
     * accent included.
     *
     * @return list<string>
     */
    public function changedFrom(self $other): array
    {
        $changed = [];

        foreach ([...$this->texts(), 'accentToken' => $this->accentToken] as $name => $value) {
            $before = [...$other->texts(), 'accentToken' => $other->accentToken][$name];

            if ($value !== $before) {
                $changed[] = $name;
            }
        }

        return $changed;
    }
}
