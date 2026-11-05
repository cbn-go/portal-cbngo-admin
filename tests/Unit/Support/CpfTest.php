<?php

namespace Tests\Unit\Support;

use App\Support\Cpf;
use PHPUnit\Framework\TestCase;

class CpfTest extends TestCase
{
    public function test_normalizes_cpf_leaving_only_eleven_digits(): void
    {
        $this->assertSame('12345678901', Cpf::normalize('123.456.789-01'));
        $this->assertSame('12345678901', Cpf::normalize('12345678901'));
        $this->assertSame('12345678901', Cpf::normalize(' 123.456.789-01 '));
        $this->assertNull(Cpf::normalize('123.456.789')); // 9 digits
        $this->assertNull(Cpf::normalize(null));
        $this->assertNull(Cpf::normalize(''));
    }

    public function test_validates_mathematically_valid_cpfs(): void
    {
        // Known valid CPFs generated according to the official algorithm
        $validCpfs = [
            '52998224725',
            '11144477735',
        ];

        foreach ($validCpfs as $cpf) {
            $this->assertTrue(Cpf::isValid($cpf), "Expected CPF {$cpf} to be valid");
            $formatted = Cpf::format($cpf);
            $this->assertNotNull($formatted);
            $this->assertTrue(Cpf::isValid($formatted), "Expected formatted CPF {$formatted} to be valid");
        }
    }

    public function test_rejects_repeated_digit_sequences(): void
    {
        $repeatedSequences = [
            '00000000000',
            '11111111111',
            '22222222222',
            '33333333333',
            '44444444444',
            '55555555555',
            '66666666666',
            '77777777777',
            '88888888888',
            '99999999999',
        ];

        foreach ($repeatedSequences as $cpf) {
            $this->assertFalse(Cpf::isValid($cpf), "Expected repeated sequence {$cpf} to be invalid");
        }
    }

    public function test_rejects_cpfs_with_invalid_checksum_digits(): void
    {
        $this->assertFalse(Cpf::isValid('52998224726')); // Last digit changed
        $this->assertFalse(Cpf::isValid('11144477730'));
        $this->assertFalse(Cpf::isValid('12345678901'));
        $this->assertFalse(Cpf::isValid('abc'));
        $this->assertFalse(Cpf::isValid(null));
    }

    public function test_formats_cpf_with_standard_mask(): void
    {
        $this->assertSame('529.982.247-25', Cpf::format('52998224725'));
        $this->assertSame('529.982.247-25', Cpf::format('529.982.247-25'));
        $this->assertNull(Cpf::format('123'));
        $this->assertNull(Cpf::format(null));
    }

    public function test_masks_cpf_keeping_only_the_last_two_digits(): void
    {
        $this->assertSame('***.***.***-25', Cpf::mask('52998224725'));
        $this->assertSame('***.***.***-25', Cpf::mask('529.982.247-25'));
        $this->assertNull(Cpf::mask('123'));
        $this->assertNull(Cpf::mask(null));
    }
}
