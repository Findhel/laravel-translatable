<?php

namespace Novius\LaravelTranslatable;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;

class LaravelTranslatableServiceProvider extends ServiceProvider
{
    public function register()
    {
    }

    public function boot()
    {
        $this->configureMacros();
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'translatable');

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/translatable'),
        ]);
    }

    protected function configureMacros()
    {
        Blueprint::macro('translatable', function ($columnLocale = 'locale', $columnParentId = 'locale_parent_id', $columnId = 'id') {
            $this->string($columnLocale, 20);
            $this->unsignedBigInteger($columnParentId)->nullable();

            $this->unique([$columnLocale, $columnParentId]);

            $this->foreign($columnParentId)
                ->references($columnId)
                ->on($this->getTable())
                ->onDelete('restrict');
        });

        Blueprint::macro('dropTranslatable', function ($columnLocale = 'locale', $columnParentId = 'locale_parent_id') {
            $this->dropForeign($this->prefix.$this->table.'_'.$columnParentId.'_foreign');
            $this->dropColumn([$columnLocale, $columnParentId]);
        });
    }
}
