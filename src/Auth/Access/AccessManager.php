<?php
namespace Dukhanin\Acl\Auth\Access;

use Dukhanin\Acl\Contracts\Auth\AccessManager as AccessManagerContract;
use Dukhanin\Support\Contracts\Morphable;

class AccessManager implements AccessManagerContract
{
    protected $delimiter = ' ';

    protected $loaded = [];

    protected $preloadEnabled = true;

    public function can(Morphable $object, string $ability, Morphable $subject = null)
    {
        $ability = $this->normalizeAbility($ability);

        $rules = $this->get($object, $ability, $subject)->first();

        return $rules ? $rules->value > 0 : null;
    }

    public function preloadEnabled(bool $switch = true)
    {
        $this->preloadEnabled = $switch;
    }

    public function preload(Morphable $object = null, string $ability = null, Morphable $subject = null)
    {
        // TODO: Implement preload() method.
    }

    public function cannot(Morphable $object, string $ability, Morphable $subject = null)
    {
        return ! $this->can(...func_get_args());
    }

    public function set(Morphable $object = null, string $ability = null, Morphable $subject = null, $value = null)
    {
        $rule = Rule::firstOrNew([
            'object_type' => $object ? $object->getMorphType() : null,
            'object_key' => $object ? $object->getMorphKey() : null,
            'ability' => $this->normalizeAbility($ability),
            'subject_type' => $subject ? $subject->getMorphType() : null,
            'subject_key' => $subject ? $subject->getMorphKey() : null,
        ]);

        if (is_null($value)) {
            $rule->exists ? $rule->delete() : null;
        } else {
            $rule->value = intval($value);
            $rule->save();
        }
    }

    /**
     * @param \Dukhanin\Support\Contracts\Morphable|null $object
     * @param string|null $ability
     * @param \Dukhanin\Support\Contracts\Morphable|null $subject
     */
    protected function get(Morphable $object = null, string $ability = null, Morphable $subject = null)
    {
        $rules = ($this->preloadEnabled ? $this->getLoaded($object, $ability, $subject) : null) ?? $this->load($object,
                $ability, $subject);

        if (! $this->isLoaded($object, $ability, $subject)) {
            $this->setLoaded($object, $ability, $subject, $rules);
        }

        return $rules;
    }

    protected function normalizeAbility(string $ability = null)
    {
        if (is_null($ability)) {
            return null;
        }

        return trim(preg_replace('/'.preg_quote($this->delimiter).'+/', $this->delimiter, $ability), $this->delimiter);
    }

    protected function getAbilityOptions(string $ability)
    {
        $parts = explode($this->delimiter, $ability);

        $options = collect();

        do {
            $options->push(implode($this->delimiter, $parts));
            array_pop($parts);
        } while (! empty($parts));

        return $options;
    }

    /**
     * @param \Dukhanin\Support\Contracts\Morphable $object
     * @param string $ability
     * @param \Dukhanin\Support\Contracts\Morphable $subject
     *
     * @return mixed
     */
    protected function load(Morphable $object = null, string $ability = null, Morphable $subject = null)
    {
        $select = Rule::whereIn('ability', $this->getAbilityOptions($ability));

        $select->whereNested(function ($select) use ($object) {
            if ($object) {
                $select->where([
                    ['object_type', $object->getMorphType(), 'and'],
                    ['object_key', $object->getMorphKey(), 'and'],
                ])->orWhere([
                    ['object_type', $object->getMorphType(), 'and'],
                    ['object_key', null, 'and'],
                ]);
            }

            $select->orWhere([
                ['object_type', null, 'and'],
                ['object_key', null, 'and'],
            ]);
        });

        $select->whereNested(function ($select) use ($subject) {
            if ($subject) {
                $select->where([
                    ['subject_type', $subject->getMorphType(), 'and'],
                    ['subject_key', $subject->getMorphKey(), 'and'],
                ])->orWhere([
                    ['subject_type', $subject->getMorphType(), 'and'],
                    ['subject_key', null, 'and'],
                ]);
            }

            $select->orWhere([
                ['subject_type', null, 'and'],
                ['subject_key', null, 'and'],
            ]);
        });

        return $select->get()->sort(function ($a, $b) {
            // аналог вложенных сортировок:
            // order by ability desc,  subject_type desc, subject_key desc

            if ($a->object_type !== $b->object_type) {
                // Null - в конец
                return is_null($a->subject_type);
            }

            if ($a->object_key !== $b->object_key) {
                // Null - в конец
                return is_null($a->object_key);
            }

            if ($a->ability !== $b->ability) {
                // Чем длиннее - тем первее
                return strcmp($b->ability, $a->ability);
            }

            if ($a->subject_type !== $b->subject_type) {
                // Null - в конец
                return is_null($a->subject_type);
            }

            // Null - в конец
            return is_null($a->subject_key);
        });
    }

    public function exact(Morphable $object, string $ability, Morphable $subject = null)
    {
        $rule = Rule::where($this->toArray($object, $ability, $subject))->first();

        return $rule ? (bool)$rule->value : null;
    }

    protected function isLoaded(Morphable $object = null, string $ability = null, Morphable $subject = null)
    {
        return array_key_exists($this->toKey($object, $ability, $subject), $this->loaded);
    }

    protected function setLoaded(
        Morphable $object = null,
        string $ability = null,
        Morphable $subject = null,
        $value = null
    ) {
        $key = $this->toKey($object, $ability, $subject);

        if (is_null($value)) {
            unset($this->loaded[$key]);
        } else {
            $this->loaded[$key] = $value;
        }
    }

    protected function getLoaded(Morphable $object = null, string $ability = null, Morphable $subject = null)
    {
        return array_get($this->loaded, $this->toKey($object, $ability, $subject));
    }

    protected function toArray(Morphable $object = null, string $ability = null, Morphable $subject = null)
    {
        return [
            'object_type' => $object ? $object->getMorphType() : null,
            'object_key' => $object ? $object->getMorphKey() : null,
            'ability' => $ability,
            'subject_type' => $subject ? $subject->getMorphType() : null,
            'subject_key' => $subject ? $subject->getMorphKey() : null,
        ];
    }

    protected function toKey(Morphable $object = null, string $ability = null, Morphable $subject = null)
    {
        return json_encode($this->toArray($object, $ability, $subject));
    }
}