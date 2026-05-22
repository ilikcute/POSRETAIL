<?php

namespace App\Jobs;

use App\Models\Sales\Sale;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendSaleConfirmationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public Sale $sale) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Mengirim email konfirmasi penjualan asinkron untuk Invoice: {$this->sale->invoice_no} kepada pelanggan.");
        // Simulate mail delivery
    }
}
