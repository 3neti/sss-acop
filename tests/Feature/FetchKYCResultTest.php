<?php

namespace Tests\Feature;

use App\KYC\Actions\FetchKYCResult;
use App\KYC\Events\KYCResultFetched;
use App\KYC\Events\KYCResultFailed;
use App\KYC\Support\ParsedKYCResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

class FetchKYCResultTest extends TestCase
{
    public function test_successfully_fetches_and_parses_kyc_result()
    {
        Event::fake();
        Http::fake([
            '*' => Http::response($this->mockKycResponse(), 200),
        ]);

        $transactionId = 'test-123';

        $result = FetchKYCResult::run($transactionId);

        $this->assertInstanceOf(ParsedKYCResult::class, $result);
        $this->assertEquals('auto_approved', $result->applicationStatus());
        $this->assertEquals('phl_dl', $result->getRaw()['idCardModule']->idType);
        $this->assertEquals('HURTADO, LESTER BIADORA', $result->getRaw()['idCardModule']->fields['fullName']);
        $this->assertEquals('phl_dl', $result->idCardModule->idType);
        $this->assertEquals('HURTADO, LESTER BIADORA', $result->idCardModule->fields['fullName']);

        Event::assertDispatched(KYCResultFetched::class);
        Event::assertNotDispatched(KYCResultFailed::class);
    }

    public function test_handles_failed_http_response()
    {
        Event::fake();
        Http::fake([
            '*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to fetch KYC result');

        FetchKYCResult::run('fail-case');

        Event::assertDispatched(KYCResultFailed::class);
        Event::assertNotDispatched(KYCResultFetched::class);
    }

    public function test_handles_request_exception()
    {
        Event::fake();
        Http::fake([
            '*' => Http::response(null, 500), // Simulate server error
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to fetch KYC result');

        FetchKYCResult::run('exception-case');

        Event::assertDispatched(KYCResultFailed::class);
        Event::assertNotDispatched(KYCResultFetched::class);
    }

    protected function mockKycResponse(): array
    {
        return json_decode(file_get_contents(base_path('tests/stubs/kyc_success_response.json')), true);
    }
}
