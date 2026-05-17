<?php

namespace App\Models\Concerns;

use App\Support\Utf8Text;

trait NormalizesUtf8Attributes
{
    public static function bootNormalizesUtf8Attributes(): void
    {
        static::saving(function (self $model): void {
            foreach ($model->normalizedUtf8Attributes() as $attribute) {
                if (! array_key_exists($attribute, $model->attributes)) {
                    continue;
                }

                $original = $model->attributes[$attribute];

                if (! is_string($original)) {
                    continue;
                }

                $model->attributes[$attribute] = Utf8Text::prepareForStorage($original) ?? $original;
            }
        });
    }

    /**
     * @return list<string>
     */
    protected function normalizedUtf8Attributes(): array
    {
        /** @var list<string>|null $attributes */
        $attributes = $this->utf8Attributes ?? null;

        return $attributes ?? [];
    }
}
