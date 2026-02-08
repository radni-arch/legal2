<?php

namespace Tests\Unit;

use App\Services\Informator\Extractors\PdfTextExtractor;
use App\Services\Informator\InformatorClient;
use Illuminate\Http\Client\Factory as HttpFactory;
use Tests\TestCase;

class InformatorParsingTest extends TestCase
{
    public function test_extracts_ids_from_listing_html(): void
    {
        $html = <<<'HTML'
<div class="js-modal-container">
  <a class="link shade no-transition block table-court__item js-checked-item" href="/sudske-odluke/1191214?hls=">
    <div class="body__cell">Example</div>
  </a>
</div>
<div class="js-modal-container">
  <a class="link shade no-transition block table-court__item" href="/sudske-odluke/42">
    <div class="body__cell">Example</div>
  </a>
</div>
HTML;

        $client = new InformatorClient(
            $this->app->make(HttpFactory::class),
            $this->createMock(PdfTextExtractor::class),
        );

        $items = $client->parseListingForItems($html);
        $ids = array_values(array_map(fn ($i) => $i['id'], $items));

        $this->assertSame(['1191214', '42'], $ids);
    }
}

