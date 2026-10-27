<?php

namespace Tests\Unit\Access;

use App\Domain\Access\Capabilities;
use App\Domain\Access\Capability;
use App\Domain\Access\Grant;
use App\Domain\Access\Role;
use InvalidArgumentException;
use Tests\Harness\Access\PermissionMatrix;
use Tests\TestCase;

/**
 * The capability map, encoded once (AC-007-02; SECURITY_MODEL section 6,
 * SEC-AZ-01, SEC-AZ-02; REQ-ACC-02; THR-03; suite AUTH01; TASK-007 scope item
 * 1): cell by cell equal to the permission matrix as SECURITY_MODEL section 6
 * writes it; the combined account exactly the union of the cashier's and
 * Operations' cells and never an Owner-only capability; SUPER_ADMIN read by
 * the Owner's column only; CAP-33 held by nobody; and a gate that names
 * something else than CAP-01 to CAP-34 opens nothing.
 */
final class CapabilityMapTest extends TestCase
{
    /** @var array<string, Role> */
    private const array ROLES = ['owner' => Role::SuperAdmin, 'ops' => Role::OperationsAdmin, 'cashier' => Role::Cashier];

    public function test_the_map_is_the_matrix_of_security_model_section_6_cell_by_cell(): void
    {
        $matrix = PermissionMatrix::cells();

        $this->assertSame(array_map(static fn (Capability $capability): string => $capability->value, Capability::cases()), array_keys($matrix), 'CAP-01 to CAP-34, in order.');

        foreach ($matrix as $identifier => $cells) {
            foreach (self::ROLES as $column => $role) {
                $this->assertSame(
                    PermissionMatrix::grantOf($cells[$column]),
                    Capabilities::ofRole($role, Capability::named($identifier))->value,
                    sprintf('%s for %s: "%s"', $identifier, $role->value, $cells[$column]),
                );
            }
        }
    }

    public function test_the_restricted_cells_are_the_three_that_the_matrix_narrows(): void
    {
        $restricted = [];

        foreach (Capability::cases() as $capability) {
            foreach (self::ROLES as $role) {
                if (Capabilities::ofRole($role, $capability) === Grant::Restricted) {
                    $restricted[] = $capability->value.' '.$role->value;
                }
            }
        }

        $this->assertSame(['CAP-02 CASHIER', 'CAP-12 OPERATIONS_ADMIN', 'CAP-14 CASHIER'], $restricted);
    }

    public function test_the_combined_account_holds_exactly_the_union_and_never_an_owner_only_capability(): void
    {
        $matrix = PermissionMatrix::cells();
        $ownerOnly = [];

        foreach (Capability::cases() as $capability) {
            $cells = $matrix[$capability->value];
            $combined = Capabilities::ofRoles([Role::Cashier, Role::OperationsAdmin], $capability);

            $this->assertSame(PermissionMatrix::grantFor($cells, 'combined'), $combined->value, $capability->value);
            $this->assertSame($combined, Capabilities::ofRoles([Role::OperationsAdmin, Role::Cashier], $capability), 'The order of the roles changes nothing.');

            if (PermissionMatrix::grantOf($cells['owner']) !== 'none' && PermissionMatrix::grantOf($cells['ops']) === 'none' && PermissionMatrix::grantOf($cells['cashier']) === 'none') {
                $ownerOnly[] = $capability->value;
                $this->assertFalse($combined->opensGate(), $capability->value.' is the Owner\'s alone (SEC-AZ-02).');
            }
        }

        $this->assertSame(['CAP-07', 'CAP-15', 'CAP-16', 'CAP-17', 'CAP-18', 'CAP-19', 'CAP-20', 'CAP-22', 'CAP-23', 'CAP-24', 'CAP-25', 'CAP-26', 'CAP-27', 'CAP-28', 'CAP-29', 'CAP-30', 'CAP-31', 'CAP-32'], $ownerOnly);
    }

    public function test_super_admin_is_read_alone_and_cap_33_is_nobodys(): void
    {
        foreach (Capability::cases() as $capability) {
            $this->assertSame(Capabilities::ofRole(Role::SuperAdmin, $capability), Capabilities::ofRoles([Role::SuperAdmin, Role::Cashier, Role::OperationsAdmin], $capability), $capability->value);
        }

        foreach ([[Role::SuperAdmin], [Role::OperationsAdmin], [Role::Cashier], [Role::Cashier, Role::OperationsAdmin], []] as $roles) {
            $this->assertSame(Grant::None, Capabilities::ofRoles($roles, Capability::Cap33));
        }

        foreach (Capability::cases() as $capability) {
            $this->assertSame(Grant::None, Capabilities::ofRoles([], $capability), 'No role, nothing.');
        }
    }

    public function test_the_flags_are_the_capabilities_that_open_a_gate_in_order(): void
    {
        $this->assertSame(
            ['CAP-01', 'CAP-02', 'CAP-03', 'CAP-04', 'CAP-05', 'CAP-06', 'CAP-08', 'CAP-09', 'CAP-10', 'CAP-11', 'CAP-13', 'CAP-14', 'CAP-34'],
            Capabilities::flags([Role::OperationsAdmin]),
        );
        $this->assertSame(['CAP-01', 'CAP-03', 'CAP-21', 'CAP-34'], Capabilities::flags([Role::Cashier]));
        $this->assertSame(
            ['CAP-01', 'CAP-02', 'CAP-03', 'CAP-04', 'CAP-05', 'CAP-06', 'CAP-08', 'CAP-09', 'CAP-10', 'CAP-11', 'CAP-13', 'CAP-14', 'CAP-21', 'CAP-34'],
            Capabilities::flags([Role::Cashier, Role::OperationsAdmin]),
        );
        $owner = Capabilities::flags([Role::SuperAdmin]);
        $this->assertCount(33, $owner);
        $this->assertNotContains('CAP-33', $owner);
    }

    public function test_a_gate_names_only_a_capability_of_the_matrix(): void
    {
        foreach (['CAP-35', 'CAP-1', 'cap-01', 'CAP-01 ', '', 'SUPER_ADMIN'] as $name) {
            try {
                Capability::named($name);
                $this->fail('Accepted: '.$name);
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(Capability::Cap21, Capability::named('CAP-21'));
    }

    public function test_the_union_of_grants_takes_the_stronger(): void
    {
        $this->assertSame(Grant::Full, Grant::Restricted->union(Grant::Full));
        $this->assertSame(Grant::Own, Grant::None->union(Grant::Own));
        $this->assertSame(Grant::Restricted, Grant::Restricted->union(Grant::None));
        $this->assertSame(Grant::None, Grant::None->union(Grant::None));
        $this->assertTrue(Grant::Own->opensGate());
        $this->assertFalse(Grant::Restricted->opensGate());
    }
}
