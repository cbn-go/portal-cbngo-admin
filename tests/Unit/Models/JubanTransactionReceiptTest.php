<?php

namespace Tests\Unit\Models;

use App\Models\JubanCategory;
use App\Models\JubanTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class JubanTransactionReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_has_receipt_returns_true_when_receipt_path_is_filled(): void
    {
        $category = JubanCategory::factory()->despesa()->create();
        $transaction = JubanTransaction::factory()->create([
            'category_id' => $category->id,
            'receipt_path' => 'juban/receipts/recibo-123.pdf',
        ]);

        $this->assertTrue($transaction->hasReceipt());
    }

    public function test_has_receipt_returns_false_when_receipt_path_is_null(): void
    {
        $category = JubanCategory::factory()->despesa()->create();
        $transaction = JubanTransaction::factory()->create([
            'category_id' => $category->id,
            'receipt_path' => null,
        ]);

        $this->assertFalse($transaction->hasReceipt());
    }

    public function test_receipt_url_returns_disk_url_when_receipt_exists(): void
    {
        Storage::fake(config('media.disk'));

        $category = JubanCategory::factory()->despesa()->create();
        $transaction = JubanTransaction::factory()->create([
            'category_id' => $category->id,
            'receipt_path' => 'juban/receipts/recibo-teste.pdf',
        ]);

        $expectedUrl = Storage::disk(config('media.disk'))->url('juban/receipts/recibo-teste.pdf');
        $this->assertSame($expectedUrl, $transaction->receiptUrl());
    }

    public function test_receipt_url_returns_null_when_no_receipt(): void
    {
        $category = JubanCategory::factory()->despesa()->create();
        $transaction = JubanTransaction::factory()->create([
            'category_id' => $category->id,
            'receipt_path' => null,
        ]);

        $this->assertNull($transaction->receiptUrl());
    }
}
