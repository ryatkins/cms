<?php

namespace Statamic\CP\Navigation;

use Statamic\Facades\CP\Nav;
use Statamic\Statamic;
use Statamic\Support\Html;
use Statamic\Support\Str;
use Statamic\Support\Traits\FluentlyGetsAndSets;

class NavItem
{
    use FluentlyGetsAndSets;

    protected $display;
    protected $section;
    protected $id;
    protected $url;
    protected $icon;
    protected $children;
    protected $authorization;
    protected $active;
    protected $view;
    protected $order;
    protected $hidden;

    /**
     * Get or set display.
     *
     * @param  string|null  $display
     * @return mixed
     */
    public function display($display = null)
    {
        return $this->fluentlyGetOrSet('display')->value($display);
    }

    /**
     * Get or set section name.
     *
     * @param  string|null  $section
     * @return mixed
     */
    public function section($section = null)
    {
        return $this->fluentlyGetOrSet('section')->value($section);
    }

    /**
     * Get or set the ID for referencing in preferences.
     *
     * @param  string|null  $id
     * @return mixed
     */
    public function id($id = null)
    {
        return $this
            ->fluentlyGetOrSet('id')
            ->setter(function ($value) {
                return Str::endsWith($value, '::')
                    ? $value.static::snakeCase($this->display())
                    : $value;
            })
            ->getter(function ($value) {
                if ($value) {
                    return $value;
                }

                $section = static::snakeCase($this->section());
                $item = static::snakeCase($this->display());

                return "{$section}::{$item}";
            })
            ->value($id);
    }

    /**
     * Set url by cp route name.
     *
     * @param  array|string  $name
     * @param  mixed  $params
     * @return mixed
     */
    public function route($name, $params = [])
    {
        return $this->url(cp_route($name, $params));
    }

    /**
     * Get or set URL.
     *
     * @param  string|null  $url
     * @return mixed
     */
    public function url($url = null)
    {
        return $this
            ->fluentlyGetOrSet('url')
            ->setter(function ($url) {
                if (Str::startsWith($url, ['http://', 'https://'])) {
                    return $url;
                }

                if (Str::startsWith($url, '/')) {
                    return url($url);
                }

                return url(config('statamic.cp.route').'/'.$url);
            })
            ->afterSetter(function ($url) {
                $cpUrl = url(config('statamic.cp.route')).'/';

                if (! $this->active && Str::startsWith($url, $cpUrl)) {
                    $this->active = str_replace($cpUrl, '', Str::before($url, '?')).'(/(.*)?|$)';
                }
            })
            ->value($url);
    }

    /**
     * Get or set icon.
     *
     * @param  string|null  $icon
     * @return mixed
     */
    public function icon($icon = null)
    {
        return $this
            ->fluentlyGetOrSet('icon')
            ->setter(function ($value) {
                return Str::startsWith($value, '<svg') ? $value : Statamic::svg($value);
            })
            ->args(func_get_args());
    }

    /**
     * Get or set HTML attributes.
     *
     * @param  array|null  $attrs
     * @return mixed
     */
    public function attributes($attrs = null)
    {
        return $this
            ->fluentlyGetOrSet('attributes')
            ->setter(function ($value) {
                return is_array($value) ? Html::attributes($value) : $value;
            })
            ->value($attrs);
    }

    /**
     * Get or set child nav items.
     *
     * @param  array|null  $items
     * @return mixed
     */
    public function children($items = null)
    {
        if (is_null($items)) {
            return $this->children;
        }

        if (is_callable($items)) {
            $this->children = $items;

            return $this;
        }

        $this->children = collect($items)
            ->map(function ($value, $key) {
                return $value instanceof self
                    ? $value
                    : Nav::item($key)->url($value);
            })
            ->map(function ($navItem) {
                return $navItem
                    ->id($this->id().'::')
                    ->icon($this->icon());
            })
            ->values();

        if ($this->children->isEmpty()) {
            $this->children = null;
        }

        return $this;
    }

    /**
     * Resolve children closure.
     *
     * @return $this
     */
    public function resolveChildren()
    {
        if (is_callable($this->children)) {
            $this->children($this->children()());
        }

        return $this;
    }

    /**
     * Get or set authorization.
     *
     * @param  string|null  $ability
     * @param  array  $arguments
     * @return mixed
     */
    public function authorization($ability = null, $arguments = [])
    {
        if (is_null($ability)) {
            return $this->authorization;
        }

        $this->authorization = (object) [
            'ability' => $ability,
            'arguments' => $arguments,
        ];

        return $this;
    }

    /**
     * Get or set authorization (an alias for consistency with Laravel's can() method).
     *
     * @param  string|null  $ability
     * @param  array  $arguments
     * @return mixed
     */
    public function can($ability = null, $arguments = [])
    {
        return $this->authorization($ability, $arguments);
    }

    /**
     * Get or set pattern for active state styling.
     *
     * @param  string|null  $pattern
     * @return mixed
     */
    public function active($pattern = null)
    {
        return $this->fluentlyGetOrSet('active')->value($pattern);
    }

    /**
     * Get whether the nav item is currently active.
     *
     * @return bool
     */
    public function isActive()
    {
        if (! $this->active) {
            return false;
        }

        $pattern = preg_quote(config('statamic.cp.route'), '#').'/'.$this->active;

        return preg_match('#'.$pattern.'#', request()->decodedPath()) === 1;
    }

    /**
     * Get or set custom view.
     *
     * @param  string|null  $view
     * @return mixed
     */
    public function view($view = null)
    {
        return $this->fluentlyGetOrSet('view')->value($view);
    }

    /**
     * Get or set nav item order.
     *
     * @param  int|null  $order
     * @return mixed
     */
    public function order($order = null)
    {
        return $this->fluentlyGetOrSet('order')->value($order);
    }

    /**
     * Get or set hidden status.
     *
     * @param  bool|null  $hidden
     * @return mixed
     */
    public function hidden($hidden = null)
    {
        return $this->fluentlyGetOrSet('hidden')
            ->getter(function ($value) {
                return $value ?? false;
            })
            ->value($hidden);
    }

    /**
     * Get whether the nav item is to be hidden, but still made available for when customizing nav.
     *
     * @return bool
     */
    public function isHidden()
    {
        return $this->hidden();
    }

    /**
     * Alias for `display()`, left here for backwards compatibility.
     *
     * @param  string|null  $name
     * @return mixed
     */
    public function name(...$arguments)
    {
        return $this->display(...$arguments);
    }

    /**
     * Convert to snake case.
     *
     * @param  string  $string
     * @return string
     */
    public static function snakeCase($string)
    {
        $string = Str::modifyMultiple($string, ['lower', 'snake']);
        $string = Str::replace($string, '-', '_');

        return $string;
    }
}
