<?php

namespace Tests\Feature\Livewire;

use App\Contracts\External\EkomServiceInterface;
use App\Http\Livewire\EkomPodnesakCreate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class EkomPodnesakCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_component_renders_successfully(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('ekom.podnesci.create'))
            ->assertOk()
            ->assertSee('Create Submission');
    }

    public function test_validates_required_fields(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(EkomPodnesakCreate::class)
            ->call('submit')
            ->assertHasErrors(['predmetId', 'vrstaPodneskaId', 'naziv']);
    }

    public function test_can_add_file_attachments(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('document.pdf', 100);

        Livewire::actingAs($user)
            ->test(EkomPodnesakCreate::class)
            ->set('attachments', [$file])
            ->assertCount('attachments', 1);
    }

    public function test_can_submit_podnesak(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->mock(EkomServiceInterface::class)
            ->shouldReceive('createPodnesak')
            ->once()
            ->andReturn(['id' => 12345, 'status' => 'kreiran']);

        Livewire::actingAs($user)
            ->test(EkomPodnesakCreate::class)
            ->set('predmetId', '123456')
            ->set('vrstaPodneskaId', '1')
            ->set('naziv', 'Test Submission')
            ->set('opis', 'Test description')
            ->call('submit')
            ->assertDispatched('success');
    }

    public function test_handles_submission_errors(): void
    {
        $user = User::factory()->create();

        $this->mock(EkomServiceInterface::class)
            ->shouldReceive('createPodnesak')
            ->once()
            ->andThrow(new \Exception('API Error'));

        Livewire::actingAs($user)
            ->test(EkomPodnesakCreate::class)
            ->set('predmetId', '123456')
            ->set('vrstaPodneskaId', '1')
            ->set('naziv', 'Test Submission')
            ->call('submit')
            ->assertDispatched('error');
    }

    public function test_requires_authentication(): void
    {
        $this->get(route('ekom.podnesci.create'))
            ->assertRedirect(route('login'));
    }

    public function test_validates_naziv_min_length(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(EkomPodnesakCreate::class)
            ->set('predmetId', '123456')
            ->set('vrstaPodneskaId', '1')
            ->set('naziv', 'ab') // Too short, must be min 3 chars
            ->call('submit')
            ->assertHasErrors(['naziv']);
    }

    public function test_validates_naziv_max_length(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(EkomPodnesakCreate::class)
            ->set('predmetId', '123456')
            ->set('vrstaPodneskaId', '1')
            ->set('naziv', str_repeat('a', 256)) // Too long, max 255 chars
            ->call('submit')
            ->assertHasErrors(['naziv']);
    }

    public function test_opis_is_optional(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->mock(EkomServiceInterface::class)
            ->shouldReceive('createPodnesak')
            ->once()
            ->andReturn(['id' => 12345, 'status' => 'kreiran']);

        Livewire::actingAs($user)
            ->test(EkomPodnesakCreate::class)
            ->set('predmetId', '123456')
            ->set('vrstaPodneskaId', '1')
            ->set('naziv', 'Test Submission')
            // opis not set - should still work
            ->call('submit')
            ->assertHasNoErrors(['opis']);
    }

    public function test_can_remove_attachment(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $file1 = UploadedFile::fake()->create('doc1.pdf', 100);
        $file2 = UploadedFile::fake()->create('doc2.pdf', 100);

        Livewire::actingAs($user)
            ->test(EkomPodnesakCreate::class)
            ->set('attachments', [$file1, $file2])
            ->assertCount('attachments', 2)
            ->call('removeAttachment', 0)
            ->assertCount('attachments', 1);
    }

    public function test_form_resets_after_successful_submission(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->mock(EkomServiceInterface::class)
            ->shouldReceive('createPodnesak')
            ->once()
            ->andReturn(['id' => 12345, 'status' => 'kreiran']);

        Livewire::actingAs($user)
            ->test(EkomPodnesakCreate::class)
            ->set('predmetId', '123456')
            ->set('vrstaPodneskaId', '1')
            ->set('naziv', 'Test Submission')
            ->set('opis', 'Test description')
            ->call('submit')
            ->assertSet('predmetId', '')
            ->assertSet('vrstaPodneskaId', '')
            ->assertSet('naziv', '')
            ->assertSet('opis', '');
    }

    public function test_displays_success_message_after_submission(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->mock(EkomServiceInterface::class)
            ->shouldReceive('createPodnesak')
            ->once()
            ->andReturn(['id' => 12345, 'status' => 'kreiran']);

        Livewire::actingAs($user)
            ->test(EkomPodnesakCreate::class)
            ->set('predmetId', '123456')
            ->set('vrstaPodneskaId', '1')
            ->set('naziv', 'Test Submission')
            ->call('submit')
            ->assertSet('successMessage', 'Submission created successfully. ID: 12345');
    }

    public function test_displays_error_message_on_failure(): void
    {
        $user = User::factory()->create();

        $this->mock(EkomServiceInterface::class)
            ->shouldReceive('createPodnesak')
            ->once()
            ->andThrow(new \Exception('Connection timeout'));

        Livewire::actingAs($user)
            ->test(EkomPodnesakCreate::class)
            ->set('predmetId', '123456')
            ->set('vrstaPodneskaId', '1')
            ->set('naziv', 'Test Submission')
            ->call('submit')
            ->assertNotSet('errorMessage', null);
    }

    public function test_uploaded_files_are_cleaned_up_after_success(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('document.pdf', 100);

        $this->mock(EkomServiceInterface::class)
            ->shouldReceive('createPodnesak')
            ->andReturn(['id' => 12345, 'status' => 'kreiran']);

        Livewire::actingAs($user)
            ->test(EkomPodnesakCreate::class)
            ->set('predmetId', '123456')
            ->set('vrstaPodneskaId', '1')
            ->set('naziv', 'Test Submission')
            ->set('attachments', [$file])
            ->call('submit');

        // Verify temporary files were cleaned up
        $this->assertEmpty(Storage::disk('local')->allFiles('ekom-uploads'));
    }

    public function test_uploaded_files_are_cleaned_up_after_failure(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('document.pdf', 100);

        $this->mock(EkomServiceInterface::class)
            ->shouldReceive('createPodnesak')
            ->andThrow(new \Exception('API Error'));

        Livewire::actingAs($user)
            ->test(EkomPodnesakCreate::class)
            ->set('predmetId', '123456')
            ->set('vrstaPodneskaId', '1')
            ->set('naziv', 'Test Submission')
            ->set('attachments', [$file])
            ->call('submit');

        // Verify files cleaned up even on failure
        $this->assertEmpty(Storage::disk('local')->allFiles('ekom-uploads'));
    }
}
