<?php

namespace Laravel\Prompts;

use Closure;
use Illuminate\Support\Collection;

class MultiSearchPrompt extends Prompt
{
    use Concerns\Truncation;
    use Concerns\TypedValue;

    /**
     * The index of the highlighted option.
     */
    public ?int $highlighted = null;

    /**
     * The cached matches.
     *
     * @var array<int|string, string>|null
     */
    protected ?array $matches = null;

    /**
     * The default values the multi-search prompt.
     *
     * @var array<int|string, string>
     */
    public array $default;

    /**
     * The selected values.
     * 
     * @var array<int|string, string>
     */
    public array $values = [];

    /**
     * Create a new MultiSearchPrompt instance.
     *
     * @param  Closure(string): array<int|string, string>  $options
     */
    public function __construct(
        public string $label,
        public Closure $options,
        array|Collection $default = [],
        public string $placeholder = '',
        public int $scroll = 5,
        public bool|string $required = false,
        public ?Closure $validate = null,
    ) {
        $this->default = $default instanceof Collection ? $default->all() : $default;
        $this->values = $this->default;
        
        $this->trackTypedValue(submit: false, allowKey: fn ($key) => $key !== Key::SPACE || $this->highlighted === null);

        $this->on('key', fn ($key) => match ($key) {
            Key::UP, Key::UP_ARROW, Key::SHIFT_TAB => $this->highlightPrevious(),
            Key::DOWN, Key::DOWN_ARROW, Key::TAB => $this->highlightNext(),
            Key::SPACE => $this->highlighted !== null ? $this->toggleHighlighted() : null,
            Key::ENTER => $this->submit(),
            Key::LEFT, Key::LEFT_ARROW, Key::RIGHT, Key::RIGHT_ARROW => $this->highlighted = null,
            default => $this->search(),
        });
    }

    protected function search(): void
    {
        $this->state = 'searching';
        $this->highlighted = null;
        $this->render();
        $this->matches = null;
        $this->state = 'active';
    }

    /**
     * Get the entered value with a virtual cursor.
     */
    public function valueWithCursor(int $maxWidth): string
    {
        if ($this->highlighted !== null) {
            return $this->typedValue === ''
                ? $this->dim($this->truncate($this->placeholder, $maxWidth))
                : $this->truncate($this->typedValue, $maxWidth);
        }

        if ($this->typedValue === '') {
            return $this->dim($this->addCursor($this->placeholder, 0, $maxWidth));
        }

        return $this->addCursor($this->typedValue, $this->cursorPosition, $maxWidth);
    }

    /**
     * Get options that match the input.
     *
     * @return array<string>
     */
    public function matches(): array
    {
        if (is_array($this->matches)) {
            return $this->matches;
        }

        return $this->matches = ($this->options)($this->typedValue);
    }

    /**
     * Highlight the previous entry, or wrap around to the last entry.
     */
    protected function highlightPrevious(): void
    {
        if ($this->matches === []) {
            $this->highlighted = null;
        } elseif ($this->highlighted === null) {
            $this->highlighted = count($this->matches) - 1;
        } elseif ($this->highlighted === 0) {
            $this->highlighted = null;
        } else {
            $this->highlighted = $this->highlighted - 1;
        }
    }

    /**
     * Highlight the next entry, or wrap around to the first entry.
     */
    protected function highlightNext(): void
    {
        if ($this->matches === []) {
            $this->highlighted = null;
        } elseif ($this->highlighted === null) {
            $this->highlighted = 0;
        } else {
            $this->highlighted = $this->highlighted === count($this->matches) - 1 ? null : $this->highlighted + 1;
        }
    }

    public function searchValue(): string
    {
        return $this->typedValue;
    }

    public function value(): array
    {
        if ($this->values === null) {
            return null;
        }

        return array_is_list($this->values)
            ? $this->values
            : array_keys($this->values);
    }

    /**
     * Get the selected label.
     */
    public function label(): ?array
    {
        return $this->matches[array_keys($this->values)[$this->highlighted]] ?? null;
    }

    /**
     * Toggle the highlighted entry.
     */
    protected function toggleHighlighted(): void
    {
        $value = array_is_list($this->matches)
            ? $this->matches[$this->highlighted]
            : array_keys($this->matches)[$this->highlighted];

        if (in_array($value, $this->values)) {
            $this->values = array_filter($this->values, fn ($v) => $v !== $value);
        } else {
            $this->values[] = $value;
        }
    }

    /**
     * Get the selected labels.
     *
     * @return array<string>
     */
    public function labels(): array
    {
        return array_values($this->values);
    }
}
