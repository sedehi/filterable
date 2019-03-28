<?php

namespace Sedehi\Filterable\Test;

use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Sedehi\Filterable\Test\Models\TestItems;

class FilterableTest extends TestCase
{

    public function setUp() : void{

        parent::setUp();
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        Schema::create('items', function(Blueprint $table){

            $table->increments('id');
            $table->string('title');
            $table->integer('number');
            $table->softDeletes();
            $table->timestamps();
        });
        TestItems::forceCreate([
                                   'title'      => 'english text',
                                   'number'     => 876,
                                   'created_at' => Carbon::create(1990, 12, 23, 10, 30, 00)
                               ]);
        TestItems::forceCreate([
                                   'title'      => 'english text',
                                   'number'     => 500,
                                   'deleted_at' => Carbon::create(2018, 10, 3, 17, 00, 30),
                                   'created_at' => Carbon::create(2018, 12, 23, 15, 00, 00)
                               ]);
        TestItems::forceCreate([
                                   'title'      => 'متن فارسی',
                                   'number'     => 210,
                                   'created_at' => Carbon::create(2018, 07, 23, 10, 30, 00)
                               ]);
    }

    /**
     * @test
     * @return void
     */
    public function can_search_with_filterable(){

        request()->replace([
                               'title' => 'navid'
                           ]);
        $this->assertCount(0, TestItems::filter()->get());
        request()->replace([
                               'title' => 'english text'
                           ]);
        $this->assertCount(1, TestItems::filter()->get());
    }

    /**
     * @test
     * @return void
     */
    public function can_set_operator(){

        $rule = [
            'title'  => [
                'operator' => 'LIKE'
            ],
            'number' => [
                'operator' => '>='
            ]
        ];
        request()->replace([
                               'title' => 'english'
                           ]);
        $this->assertCount(1, TestItems::filter($rule)->get());
        request()->replace([
                               'number' => '210'
                           ]);
        $this->assertCount(2, TestItems::filter($rule)->get());
    }

    /**
     * @test
     * @return void
     */
    public function can_find_items_with_trashed(){

        request()->replace([
                               'trashed' => 'with'
                           ]);
        $this->assertCount(3, TestItems::filter()->get());
    }

    /**
     * @test
     * @return void
     */
    public function can_find_items_only_trashed(){

        request()->replace([
                               'trashed' => 'only'
                           ]);
        $this->assertCount(1, TestItems::filter()->get());
    }

    /**
     * @test
     * @return void
     */
    public function search_by_scope(){

        request()->replace([
                               'custom'
                           ]);
        $this->assertCount(2, TestItems::filter()->get());
    }

    /**
     * @test
     * @return void
     */
    public function can_search_gregorian_datetime(){

        config()->set('filterable.date_type', 'gregorian');
        request()->replace([
                               'start_created' => '2018-12-23',
                               'end_created'   => '2018-12-24',
                               'trashed'       => 'with'
                           ]);
        $this->assertCount(1, TestItems::filter()->get());
    }

    /**
     * @test
     * @return void
     */
    public function can_search_jalali_datetime(){

        config()->set('filterable.date_type', 'jalali');
        request()->replace([
                               'start_created' => '1369-10-02',
                               'end_created'   => '1369-10-02',
                           ]);
        $this->assertCount(1, TestItems::filter()->get());
    }
}
