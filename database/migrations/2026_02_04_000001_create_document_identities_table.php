<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 7 - Task 38: DocumentIdentity model + migration
 *
 * Multi-dimensional document tracking for Croatian legal documents.
 * Tracks case numbers, KLASA, URBROJ, and identifies present vs missing documents.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('document_identities', function (Blueprint $table) {
            $table->id();
            $table->string('case_id');

            // Link to actual uploaded document (nullable - for missing docs)
            $table->unsignedBigInteger('case_document_id')->nullable();

            // === LAYER 1: Case/Metacase Identity ===
            $table->string('case_number')->nullable();        // "Pp Prz-74/2025"
            $table->string('case_prefix')->nullable();        // "Pp Prz"
            $table->integer('case_seq_number')->nullable();   // 74
            $table->integer('case_year')->nullable();          // 2025
            $table->integer('case_suffix')->nullable();        // 3 (from "-3")
            $table->string('case_number_full')->nullable();   // "Pp Prz-74/2025-3"

            // === LAYER 2: Administrative Identity ===
            $table->string('klasa')->nullable();               // "UP/I-034-02/25-01/5"
            $table->string('urbroj')->nullable();              // "2158-64-16-01-25-3"
            $table->string('urbroj_institution_code')->nullable(); // "2158" (parsed)
            $table->integer('urbroj_suffix')->nullable();      // 3 (parsed from end)

            // === LAYER 3: Internal Number ===
            $table->string('broj')->nullable();                // "511-07-11-K-51/2025"
            $table->string('broj_type')->nullable();           // "policijski", "drzavno_odvjetnistvo"

            // === LAYER 4: Derived Metadata ===
            $table->date('document_date')->nullable();         // Date found in/on the document
            $table->string('document_type')->nullable();       // "rjesenje", "zapisnik", "naredba", etc.
            $table->string('issuing_institution')->nullable(); // "Opcinski sud u Osijeku"
            $table->string('metacase_role')->nullable();       // "search_warrant", "detention", etc.

            // === Status ===
            $table->string('presence_status');                  // "present", "missing", "partial"
            // present  = we have the actual file
            // missing  = referenced in other docs but not in our file
            // partial  = OCR failed or file corrupted

            // How was this identity discovered?
            $table->string('discovery_source')->default('extraction'); // "extraction", "inference", "manual"
            // extraction = found identifiers on this document
            // inference  = gap in sequence implies existence
            // manual     = user added it

            // References: which documents mention this identity
            $table->json('referenced_in_documents')->nullable(); // [doc_id, doc_id, ...]
            $table->integer('reference_count')->default(0);

            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['case_id', 'presence_status']);
            $table->index(['case_id', 'case_number']);
            $table->index(['case_id', 'klasa']);
            $table->index(['case_number', 'case_suffix']);

            // A document can only have one identity row per case_number_full
            $table->unique(['case_id', 'case_number_full'], 'doc_identity_case_unique');

            $table->foreign('case_document_id')
                ->references('id')->on('court_case_documents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_identities');
    }
};
