<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Customer;
use App\Models\DocumentFile;
use App\Models\Tag;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_document_and_a_box_can_share_a_tag(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        $tag = Tag::create(['customer_id' => $customer->id, 'name' => 'Urgent', 'color' => '#ff0000']);

        $file = DocumentFile::create([
            'customer_id' => $customer->id,
            'file_barcode' => 'DOC-1',
            'title' => 'Contract',
            'current_status' => 'active',
        ]);
        $box = Box::create(['customer_id' => $customer->id, 'box_number' => 'B1', 'box_barcode' => 'BC-B1', 'status' => 'active']);

        $file->tags()->attach($tag);
        $box->tags()->attach($tag);

        $this->assertTrue($file->tags->contains($tag));
        $this->assertTrue($box->tags->contains($tag));
        $this->assertTrue($tag->documentFiles->contains($file));
        $this->assertTrue($tag->boxes->contains($box));
    }

    public function test_tag_names_are_unique_per_customer(): void
    {
        $customer = Customer::create(['company_name' => 'Acme', 'company_code' => 'ACM', 'status' => 'active']);
        Tag::create(['customer_id' => $customer->id, 'name' => 'Urgent']);

        $this->expectException(QueryException::class);
        Tag::create(['customer_id' => $customer->id, 'name' => 'Urgent']);
    }
}
