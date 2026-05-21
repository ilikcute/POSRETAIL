<?php

namespace App\Services;

use App\Models\Sales\Sale;
use Exception;
use Illuminate\Support\Facades\Log;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;

class ThermalPrintService
{
    /**
     * Format dan cetak Struk Penjualan POS ke Printer Thermal (ESC/POS)
     *
     * @param  int  $saleId  ID Penjualan
     * @param  string  $connectorTipe  Tipe koneksi: 'network', 'windows', 'file'
     * @param  string  $target  Target koneksi: '192.168.1.100', 'LPT1', 'USB001', '/dev/usb/lp0'
     * @param  int  $paperWidth  Lebar Kertas: 32 (untuk 58mm) atau 48 (untuk 80mm)
     * @return array Status cetak
     */
    public function printReceipt(int $saleId, string $connectorTipe = 'network', string $target = '192.168.1.200', int $paperWidth = 32): array
    {
        try {
            // Menggunakan relation 'items' dan 'amount_paid' yang sesuai dengan model Sale
            $sale = Sale::with(['items.product', 'customer', 'warehouse', 'shift.user'])->findOrFail($saleId);

            // 1. Inisialisasi Print Connector yang Sesuai
            $connector = null;
            switch (strtolower($connectorTipe)) {
                case 'network': // Printer Wifi / Ethernet (Paling umum untuk Resto/Supermarket modern)
                    $connector = new NetworkPrintConnector($target, 9100);
                    break;
                case 'windows': // Printer USB / LPT di Windows (di-share/spooler)
                    $connector = new WindowsPrintConnector($target);
                    break;
                case 'file': // Printer USB di Linux/Unix atau Virtual Serial COM Port
                    $connector = new FilePrintConnector($target);
                    break;
                default:
                    throw new Exception("Tipe konektor thermal printer tidak dikenal: {$connectorTipe}");
            }

            // 2. Inisialisasi ESC/POS Printer
            $printer = new Printer($connector);

            // 3. Bangun Layout Struk POS Retail
            // A. Judul / Header Toko
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH | Printer::MODE_DOUBLE_HEIGHT);
            $printer->text(($sale->warehouse->name ?? 'POSRETAIL STORE')."\n");

            $printer->selectPrintMode(); // Reset to normal
            $printer->text("Telp: 0812-3456-7890\n");
            $printer->text('Lokasi: '.($sale->warehouse->address ?? 'Gudang/Toko Pusat')."\n");
            $printer->text(str_repeat('=', $paperWidth)."\n");

            // B. Metadata Transaksi (No. Invoice, Kasir, Tanggal)
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text('Invoice : '.$sale->invoice_no."\n");
            $printer->text('Tanggal : '.$sale->created_at->format('d/m/Y H:i')."\n");
            $printer->text('Kasir   : '.($sale->shift->user->name ?? 'Kasir Utama')."\n");

            if ($sale->customer) {
                $printer->text('Member  : '.$sale->customer->name."\n");
            }
            $printer->text(str_repeat('-', $paperWidth)."\n");

            // C. Itemized Products (Nama barang & Kuantitas x Harga)
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            foreach ($sale->items as $item) {
                $productName = $item->product->name;

                // Potong nama produk jika terlalu panjang agar pas dengan lebar baris
                if (strlen($productName) > ($paperWidth - 10)) {
                    $productName = substr($productName, 0, $paperWidth - 12).'..';
                }

                $printer->text($productName."\n");

                // Format: " 2 x 12.000             24.000"
                $qtyPart = ' '.round($item->qty, 0).' x '.number_format($item->unit_price, 0, ',', '.');
                $totalPart = number_format($item->subtotal, 0, ',', '.');

                $spaceCount = $paperWidth - strlen($qtyPart) - strlen($totalPart);
                $spaceCount = max(1, $spaceCount);

                $printer->text($qtyPart.str_repeat(' ', $spaceCount).$totalPart."\n");
            }
            $printer->text(str_repeat('-', $paperWidth)."\n");

            // D. Ringkasan Pembayaran (Subtotal, Diskon Promo/Loyalty, Pajak, Grand Total)
            $subtotalText = number_format($sale->total_amount, 0, ',', '.');
            $discountText = number_format($sale->discount_amount, 0, ',', '.');
            $taxText = number_format($sale->tax_amount, 0, ',', '.');
            $grandText = number_format($sale->grand_total, 0, ',', '.');
            $paidText = number_format($sale->amount_paid, 0, ',', '.');
            $changeText = number_format($sale->change_amount, 0, ',', '.');

            // Cetak Baris Perhitungan
            $this->printReceiptLine($printer, 'Subtotal', $subtotalText, $paperWidth);

            if ($sale->discount_amount > 0) {
                $this->printReceiptLine($printer, 'Diskon', '-'.$discountText, $paperWidth);
            }

            if (isset($sale->points_discount) && $sale->points_discount > 0) {
                $this->printReceiptLine($printer, 'Pot. Poin Loyalty', '-'.number_format($sale->points_discount, 0, ',', '.'), $paperWidth);
            }

            if ($sale->tax_amount > 0) {
                $this->printReceiptLine($printer, 'Pajak (PPN)', $taxText, $paperWidth);
            }

            $printer->text(str_repeat('-', $paperWidth)."\n");

            // Grand Total (Bold / Double Width)
            $printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
            $this->printReceiptLine($printer, 'TOTAL', $grandText, $paperWidth / 2);
            $printer->selectPrintMode(); // reset to normal

            $this->printReceiptLine($printer, 'Bayar ('.strtoupper($sale->payment_method).')', $paidText, $paperWidth);
            $this->printReceiptLine($printer, 'Kembalian', $changeText, $paperWidth);
            $printer->text(str_repeat('=', $paperWidth)."\n");

            // E. Info Loyalty Poin (Sinkronisasi Cashback & Poin Ritel!)
            if ($sale->customer) {
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->text("LOYALTY MEMBER POINT CARD\n");
                if (isset($sale->points_earned)) {
                    $printer->text('Didapat hari ini: +'.number_format($sale->points_earned, 0, ',', '.')." Pts\n");
                }

                if (isset($sale->points_redeemed) && $sale->points_redeemed > 0) {
                    $printer->text('Ditukarkan hari ini: -'.number_format($sale->points_redeemed, 0, ',', '.')." Pts\n");
                }

                $printer->text('Saldo Poin Akhir: '.number_format($sale->customer->point_balance, 0, ',', '.')." Pts\n");
                $printer->text(str_repeat('-', $paperWidth)."\n");
            }

            // F. Footer & Thank You Note
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("Terima Kasih Atas Kunjungan Anda\n");
            $printer->text("Barang Yang Sudah Dibeli\n");
            $printer->text("Tidak Dapat Ditukar/Dikembalikan\n");
            $printer->text("Powered by POSRETAIL ERP v1.0\n");

            // Feed kertas dan potong
            $printer->feed(4);
            $printer->cut();

            // Tutup koneksi printer
            $printer->close();

            Log::info("Thermal print receipt successful for Sale ID: {$saleId}");

            return [
                'success' => true,
                'message' => 'Receipt printed successfully',
            ];

        } catch (Exception $e) {
            Log::error('Thermal print receipt failed: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Gagal mencetak struk: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Helper untuk mencetak baris dengan perataan kiri-kanan pada kertas thermal
     */
    protected function printReceiptLine(Printer $printer, string $label, string $value, int $paperWidth): void
    {
        $spaceCount = $paperWidth - strlen($label) - strlen($value);
        $spaceCount = max(1, $spaceCount);
        $printer->text($label.str_repeat(' ', $spaceCount).$value."\n");
    }
}
