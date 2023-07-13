<?php

namespace Novius\LaravelTranslatable\Traits;

use Illuminate\Database\Eloquent\Model;
use Novius\LaravelTranslatable\Exceptions\TranslatableException;

/**
 * @property-read \Illuminate\Database\Eloquent\Collection<int, static> $translations
 * @property-read int|null $translations_count
 */
trait Translatable
{
    protected ?Model $parentToSave = null;

    /**
     * Boot the translatable trait for a model.
     */
    public static function bootTranslatable(): void
    {
        static::creating(static function (Model $model) {
            /** @var Model&Translatable $model */
            $localeColumn = $model->getLocaleColumn();
            $localeParentIdColumn = $model->getLocaleParentIdColumn();
            $locale = $model->{$localeColumn};
            $locale_parent_id = $model->{$localeParentIdColumn};

            if ($locale_parent_id) {
                $parent = $model::query()
                    ->with('translations')
                    ->where($model->getKeyName(), $locale_parent_id)
                    ->first();
                if ($parent === null) {
                    $model->{$localeParentIdColumn} = null;
                } else {
                    $translation = $model->getTranslation($locale);
                    if ($translation !== null) {
                        throw new TranslatableException(trans('translatable::messages.already_translated'));
                    }

                    $model->{$localeParentIdColumn} = $parent->{$localeParentIdColumn} ?? $parent->{$model->getKeyName()};

                    if (empty($parent->{$localeParentIdColumn})) {
                        $model->parentToSave = $parent;
                    }
                }
            }
        });
        static::created(static function (Model $model) {
            /** @var Model&Translatable $model */
            $localeParentIdColumn = $model->getLocaleParentIdColumn();

            if ($model->parentToSave !== null) {
                $model->parentToSave->{$localeParentIdColumn} = $model->{$localeParentIdColumn};
                if (! $model->parentToSave->save()) {
                    throw new TranslatableException(trans('translatable::messages.error_during_translation'));
                }
            }
        });
    }

    public function translations()
    {
        return $this->hasMany(static::class, $this->getLocaleParentIdColumn(), $this->getLocaleParentIdColumn());
    }

    public function translate(string $locale, array $translateAttributes = []): static
    {
        $localeColumn = $this->getLocaleColumn();
        $localeParentIdColumn = $this->getLocaleParentIdColumn();

        if (! empty($this->{$localeParentIdColumn})) {
            $otherPageAlreadyExists = $this->query()
                ->where($localeColumn, $locale)
                ->where($localeParentIdColumn, $this->{$localeParentIdColumn})
                ->exists();

            if ($otherPageAlreadyExists) {
                throw new TranslatableException(trans('translatable::messages.already_translated'));
            }
        } elseif ($this->{$localeColumn} === $locale) {
            throw new TranslatableException(trans('translatable::messages.already_translated'));
        }

        $localeParentId = $this->{$localeParentIdColumn} ?? $this->{$this->getKeyName()};

        $translatedItem = $this->replicate();
        foreach ($translateAttributes as $translateAttribute => $value) {
            $translatedItem->{$translateAttribute} = $value;
        }
        $translatedItem->{$localeColumn} = $locale;
        $translatedItem->{$localeParentIdColumn} = $localeParentId;

        $this->getConnection()->beginTransaction();

        if (! $translatedItem->save()) {
            $this->getConnection()->rollBack();
            throw new TranslatableException(trans('translatable::messages.error_during_translation'));
        }

        if (empty($this->{$localeParentIdColumn})) {
            $this->{$localeParentIdColumn} = $localeParentId;
            if (! $this->save()) {
                $this->getConnection()->rollBack();
                throw new TranslatableException(trans('translatable::messages.error_during_translation'));
            }
        }

        $this->getConnection()->commit();

        return $translatedItem;
    }

    public function getTranslation(string $locale): static|null
    {
        return $this->translations()->where($this->getLocaleColumn(), $locale)->first();
    }

    /**
     * Get the name of the "publication status" column.
     */
    public function getLocaleColumn(): string
    {
        return defined('static::LOCALE') ? static::LOCALE : 'locale';
    }

    /**
     * Get the name of the "published first at" column.
     */
    public function getLocaleParentIdColumn(): string
    {
        return defined('static::LOCALE_PARENT_ID') ? static::LOCALE_PARENT_ID : 'locale_parent_id';
    }

    /**
     * Get the fully qualified "publication status" column.
     */
    public function getQualifiedLocaleColumn(): string
    {
        return $this->qualifyColumn($this->getLocaleColumn());
    }

    /**
     * Get the fully qualified "published first at" column.
     */
    public function getQualifiedLocaleParentIdColumn(): string
    {
        return $this->qualifyColumn($this->getLocaleParentIdColumn());
    }
}
