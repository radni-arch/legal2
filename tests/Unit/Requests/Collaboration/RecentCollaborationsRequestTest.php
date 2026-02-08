<?php

namespace Tests\Unit\Requests\Collaboration;

use App\Http\Requests\Collaboration\RecentCollaborationsRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class RecentCollaborationsRequestTest extends TestCase
{
    private function validate(array $data): \Illuminate\Validation\Validator
    {
        $request = new RecentCollaborationsRequest;

        return Validator::make($data, $request->rules(), $request->messages());
    }

    public function test_valid_without_limit(): void
    {
        $data = [];

        $validator = $this->validate($data);
        $this->assertTrue($validator->passes());
    }

    public function test_valid_with_limit(): void
    {
        $data = ['limit' => 10];

        $validator = $this->validate($data);
        $this->assertTrue($validator->passes());
    }

    public function test_valid_with_minimum_limit(): void
    {
        $data = ['limit' => 1];

        $validator = $this->validate($data);
        $this->assertTrue($validator->passes());
    }

    public function test_valid_with_maximum_limit(): void
    {
        $data = ['limit' => 100];

        $validator = $this->validate($data);
        $this->assertTrue($validator->passes());
    }

    public function test_limit_must_be_integer(): void
    {
        $data = ['limit' => 'not an integer'];

        $validator = $this->validate($data);
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('limit', $validator->errors()->toArray());
    }

    public function test_limit_must_be_at_least_one(): void
    {
        $data = ['limit' => 0];

        $validator = $this->validate($data);
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('limit', $validator->errors()->toArray());

        $data['limit'] = -1;
        $validator = $this->validate($data);
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('limit', $validator->errors()->toArray());
    }

    public function test_limit_cannot_exceed_100(): void
    {
        $data = ['limit' => 101];

        $validator = $this->validate($data);
        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('limit', $validator->errors()->toArray());
    }

    public function test_authorization_always_true(): void
    {
        $request = new RecentCollaborationsRequest;
        $this->assertTrue($request->authorize());
    }
}
