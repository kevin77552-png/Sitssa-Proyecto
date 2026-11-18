<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Etiquetas de inventario</title>
    @vite('resources/js/app.js')
    <style>
        @page {
            size: A4;
            margin: 15.1mm 7.25mm 15.2mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f1f5f9;
            color: #0f172a;
            font-family: Arial, sans-serif;
        }

        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.5rem;
            background: #fff;
            box-shadow: 0 1px 3px #0f172a1a;
        }

        .toolbar p {
            margin: 0;
            color: #475569;
            font-size: 0.875rem;
        }

        .toolbar button {
            border: 0;
            border-radius: 0.5rem;
            padding: 0.65rem 1rem;
            background: #9f1239;
            color: #fff;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .toolbar button:disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }

        .barcode-error-summary {
            margin: 1rem 1.5rem;
            color: #be123c;
            font-size: 0.875rem;
        }

        .sheet {
            display: grid;
            width: 195.5mm;
            height: 266.7mm;
            margin: 1.5rem auto;
            grid-template-columns: repeat(3, 63.5mm);
            grid-template-rows: repeat(7, 38.1mm);
            column-gap: 2.5mm;
            background: #fff;
            box-shadow: 0 2px 12px #0f172a1a;
        }

        .label {
            display: flex;
            width: 63.5mm;
            height: 38.1mm;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            overflow: hidden;
            border: 0.15mm dashed #cbd5e1;
            padding: 1.5mm 2mm;
            text-align: center;
        }

        .description {
            display: -webkit-box;
            max-width: 100%;
            overflow: hidden;
            color: #334155;
            font-size: 7pt;
            line-height: 1.15;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
        }

        .barcode {
            display: block;
            max-width: 100%;
            max-height: 18mm;
        }

        .sigicov {
            color: #0f172a;
            font-family: monospace;
            font-size: 9pt;
            font-weight: 700;
            letter-spacing: 0.08em;
        }

        .barcode-error {
            color: #be123c;
            font-size: 6pt;
        }

        @media print {
            body {
                background: #fff;
                print-color-adjust: exact;
            }

            .toolbar,
            .barcode-error-summary {
                display: none !important;
            }

            .sheet {
                margin: 0;
                box-shadow: none;
                break-after: page;
                page-break-after: always;
            }

            .sheet:last-child {
                break-after: auto;
                page-break-after: auto;
            }

            .label {
                border-color: transparent;
            }
        }
    </style>
</head>
<body>
    <header class="toolbar">
        <p>{{ $items->count() }} etiquetas · A4 · 21 por hoja · Imprime al 100% y desactiva encabezados y pies</p>
        <button id="print-labels" type="button" disabled>Imprimir / Guardar como PDF</button>
    </header>

    <p id="barcode-error-summary" class="barcode-error-summary" hidden>No se pudieron generar todos los códigos. No imprimas esta hoja; actualiza la página o verifica los recursos de la aplicación.</p>

    <main>
        @foreach($items->chunk(21) as $page)
            <section class="sheet" aria-label="Hoja de etiquetas">
                @foreach($page as $item)
                    <article class="label">
                        <div class="description">{{ $item->descripcion ?: 'Repuesto' }}</div>
                        <svg class="barcode" data-sigicov="{{ $item->sigicov }}" role="img" aria-label="Código de barras SIGICOV {{ $item->sigicov }}"></svg>
                        <div class="sigicov">{{ $item->sigicov }}</div>
                        <div class="barcode-error" data-barcode-error hidden>No se pudo generar este código.</div>
                    </article>
                @endforeach
                @for($emptyLabel = $page->count(); $emptyLabel < 21; $emptyLabel++)
                    <div class="label" aria-hidden="true"></div>
                @endfor
            </section>
        @endforeach
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const printButton = document.getElementById('print-labels');
            const errorSummary = document.getElementById('barcode-error-summary');
            const hasRenderer = typeof window.renderInventoryBarcode === 'function';
            let hasError = !hasRenderer;

            document.querySelectorAll('[data-sigicov]').forEach((barcode) => {
                if (!hasRenderer || !window.renderInventoryBarcode(barcode, barcode.dataset.sigicov)) {
                    barcode.parentElement.querySelector('[data-barcode-error]').hidden = false;
                    hasError = true;
                }
            });

            printButton.disabled = hasError;
            errorSummary.hidden = !hasError;
            printButton.addEventListener('click', () => window.print());
        });
    </script>
</body>
</html>
