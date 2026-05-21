@php
    $size = request('size', 'sedang');
    $columns = 3;
    if ($size === 'besar') {
        $columns = 1;
    } elseif ($size === 'sedang') {
        $columns = 2;
    }
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Price Tag - POSRETAIL</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&display=swap');
        
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 20px;
            color: #1f2937;
        }

        .no-print-zone {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #1e3a8a, #3b82f6);
            color: white;
            padding: 15px 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.3);
        }

        .btn-print {
            background-color: #ef4444;
            color: white;
            border: none;
            padding: 12px 24px;
            font-weight: 700;
            font-size: 15px;
            border-radius: 8px;
            cursor: pointer;
            box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.4);
            transition: all 0.3s;
        }

        .btn-print:hover {
            background-color: #dc2626;
            transform: translateY(-2px);
        }

        /* Container grid A4 */
        .tags-container {
            display: grid;
            grid-template-columns: repeat({{ $columns }}, 1fr);
            gap: 20px;
            max-width: 1000px;
            margin: 0 auto;
        }

        /* Size-specific tags adjustments */
        
        /* Format BESAR (Self-taker, 1 column) */
        .tags-container.size-besar {
            max-width: 650px;
            gap: 30px;
        }
        .size-besar .price-tag {
            min-height: 280px;
            padding: 24px;
            border-radius: 16px;
            border-width: 3px;
        }
        .size-besar .promo-banner {
            font-size: 16px;
            padding: 6px 0;
        }
        .size-besar .tag-header {
            font-size: 14px;
            margin-top: 20px;
        }
        .size-besar .product-name {
            font-size: 24px;
            height: 60px;
            margin-top: 10px;
        }
        .size-besar .price-normal {
            font-size: 46px;
        }
        .size-besar .price-promo {
            font-size: 50px;
        }
        .size-besar .price-strike {
            font-size: 22px;
        }
        .size-besar .barcode-lines {
            width: 160px;
            height: 35px;
        }
        .size-besar .barcode-text {
            font-size: 13px;
        }
        .size-besar .rack-location {
            font-size: 15px;
            padding: 4px 10px;
        }
        .size-besar .tag-notes {
            font-size: 12px;
        }

        /* Format SEDANG (Large Volume Shelf, 2 columns) */
        .tags-container.size-sedang {
            max-width: 850px;
            gap: 20px;
        }
        .size-sedang .price-tag {
            min-height: 190px;
            padding: 16px;
        }
        .size-sedang .promo-banner {
            font-size: 13px;
            padding: 5px 0;
        }
        .size-sedang .tag-header {
            font-size: 12px;
            margin-top: 12px;
        }
        .size-sedang .product-name {
            font-size: 18px;
            height: 44px;
        }
        .size-sedang .price-normal {
            font-size: 32px;
        }
        .size-sedang .price-promo {
            font-size: 36px;
        }
        .size-sedang .price-strike {
            font-size: 16px;
        }
        .size-sedang .barcode-lines {
            width: 110px;
            height: 24px;
        }
        .size-sedang .barcode-text {
            font-size: 11px;
        }
        .size-sedang .rack-location {
            font-size: 12px;
            padding: 3px 8px;
        }
        .size-sedang .tag-notes {
            font-size: 10px;
        }

        /* Format KECIL (Small Volume Shelf, 3 columns - default) */
        .tags-container.size-kecil {
            max-width: 1000px;
            gap: 15px;
        }

        /* Desain Price Tag Premium */
        .price-tag {
            background-color: white;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 150px;
        }

        /* Highlight jika ada Promo */
        .price-tag.promo {
            border: 3px solid #ef4444;
            background-color: #fffbeb;
        }

        .promo-banner {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            background-color: #ef4444;
            color: white;
            font-weight: 800;
            font-size: 12px;
            text-align: center;
            padding: 4px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .tag-header {
            margin-top: 15px; /* space for promo banner if present */
            font-size: 11px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            display: flex;
            justify-content: space-between;
        }

        .tag-body {
            margin: 8px 0;
        }

        .product-name {
            font-size: 15px;
            font-weight: 700;
            color: #111827;
            line-height: 1.2;
            height: 36px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .price-area {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            margin-top: 10px;
        }

        /* Desain Angka Harga Normal */
        .price-normal {
            font-size: 26px;
            font-weight: 800;
            color: #111827;
        }

        /* Desain Coret & Harga Promo */
        .price-strike {
            font-size: 14px;
            text-decoration: line-through;
            color: #9ca3af;
            font-weight: 600;
            margin-right: 8px;
        }

        .price-promo {
            font-size: 28px;
            font-weight: 800;
            color: #ef4444;
        }

        .unit-info {
            font-size: 11px;
            font-weight: 600;
            color: #6b7280;
        }

        .tag-footer {
            border-top: 1px dashed #d1d5db;
            padding-top: 8px;
            margin-top: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .barcode-area {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        /* Simulasi Garis Barcode Ritel */
        .barcode-lines {
            width: 80px;
            height: 18px;
            background: repeating-linear-gradient(
                90deg,
                #111827,
                #111827 2px,
                transparent 2px,
                transparent 5px,
                #111827 5px,
                #111827 7px
            );
        }

        .barcode-text {
            font-size: 9px;
            font-weight: 600;
            color: #4b5563;
            letter-spacing: 1px;
            margin-top: 2px;
        }

        .rack-location {
            background-color: #374151;
            color: white;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            text-transform: uppercase;
        }

        .tag-notes {
            font-size: 9px;
            color: #6b7280;
            font-weight: 500;
            margin-top: 2px;
        }

        /* Pengaturan Cetak Media A4 */
        @media print {
            body {
                background-color: white;
                padding: 0;
            }

            .no-print-zone {
                display: none;
            }

            .tags-container {
                gap: 15px;
                max-width: 100%;
            }

            .price-tag {
                box-shadow: none;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

    <div class="no-print-zone">
        <div>
            <h1 style="margin: 0; font-size: 22px; font-weight: 800; letter-spacing: 0.5px;">Cetak Label Harga (Price Tag)</h1>
            <p style="margin: 5px 0 0 0; font-size: 13px; opacity: 0.85;">Silakan tekan tombol Cetak untuk mencetak label harga yang siap ditempelkan pada Rak.</p>
        </div>
        <button class="btn-print" onclick="window.print()">CETAK SEKARANG</button>
    </div>

    <div class="tags-container size-{{ $size }}">
        @foreach($priceTags as $tag)
            <div class="price-tag {{ $tag['is_promo_active'] ? 'promo' : '' }}">
                
                @if($tag['is_promo_active'])
                    <div class="promo-banner">{{ $tag['promo_discount_text'] }}</div>
                @endif

                <div class="tag-header">
                    <span>{{ $tag['category_name'] }}</span>
                    <span>per {{ $tag['unit_name'] }}</span>
                </div>

                <div class="tag-body">
                    <div class="product-name">{{ $tag['product_name'] }}</div>
                    
                    <div class="price-area">
                        @if($tag['is_promo_active'])
                            <div>
                                <span class="price-strike">Rp {{ number_format($tag['normal_price'], 0, ',', '.') }}</span>
                                <span class="price-promo">Rp {{ number_format($tag['promo_price'], 0, ',', '.') }}</span>
                            </div>
                        @else
                            <span class="price-normal">Rp {{ number_format($tag['normal_price'], 0, ',', '.') }}</span>
                        @endif
                    </div>
                </div>

                <div class="tag-footer">
                    <div class="barcode-area">
                        <div class="barcode-lines"></div>
                        <div class="barcode-text">{{ $tag['barcode'] }}</div>
                        @if($tag['is_promo_active'])
                            <div class="tag-notes">Promo {{ $tag['promo_end_date'] }}</div>
                        @endif
                    </div>
                    <div class="rack-location">{{ $tag['rack_code'] }}</div>
                </div>

            </div>
        @endforeach
    </div>

</body>
</html>
