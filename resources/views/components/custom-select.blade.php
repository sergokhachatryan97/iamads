@props([
    'name',
    'id' => null,
    'value' => null,
    'placeholder' => 'Select an option',
    'label' => null,
    'options' => [],
    'size' => 'default', // 'default' or 'small'
])

@php
    $selectId = $id ?? $name . '_select';
    $dropdownId = $selectId . '_dropdown';
    $hiddenInputId = $selectId . '_hidden';

    // Normalize options format - accept both ['key' => 'label'] and [['value' => 'key', 'label' => 'label']]
    $normalizedOptions = [];
    foreach ($options as $key => $option) {
        if (is_array($option) && isset($option['value'])) {
            // Already in the correct format
            $normalizedOptions[] = $option;
        } else {
            // Convert ['key' => 'label'] format to [['value' => 'key', 'label' => 'label']]
            $normalizedOptions[] = [
                'value' => $key,
                'label' => $option,
            ];
        }
    }

    // Find the selected option based on value
    $selectedOption = null;
    if ($value !== null && $value !== '') {
        $selectedOption = collect($normalizedOptions)->firstWhere('value', $value);
    }

    // Get the label for the selected option
    $selectedLabel = $selectedOption ? ($selectedOption['label'] ?? $selectedOption['value'] ?? '') : '';
@endphp

<div
    x-data="{
        open: false,
        selectedValue: @js($value ?? ''),
        selectedLabel: @js($selectedLabel),
        options: @js($normalizedOptions),
        focusedIndex: -1,

        get placeholder() {
            return @js($placeholder);
        },

        toggle() {
            this.open = !this.open;
            if (this.open) {
                this.$nextTick(() => {
                    this.scrollToSelected();
                });
            }
        },

        close() {
            this.open = false;
            this.focusedIndex = -1;
        },

        selectOption(option) {
            this.selectedValue = option.value;
            this.selectedLabel = option.label || option.value || '';
            this.close();

            // Update hidden input
            const hiddenInput = this.$refs.hiddenInput;
            if (hiddenInput) {
                hiddenInput.value = option.value;
                // Dispatch change event
                hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                // Dispatch custom event for filter count updates
                this.$dispatch('filter-value-changed', { name: hiddenInput.name, value: option.value });
            }
        },

        findSelectedIndex() {
            return this.options.findIndex(opt => String(opt.value) === String(this.selectedValue));
        },

        scrollToSelected() {
            const selectedIndex = this.findSelectedIndex();
            if (selectedIndex >= 0) {
                this.focusedIndex = selectedIndex;
                this.$nextTick(() => {
                    const optionElement = this.$el.querySelector('[data-option-index=\'' + selectedIndex + '\']');
                    if (optionElement) {
                        optionElement.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                    }
                });
            }
        },

        handleKeydown(event) {
            if (!this.open) {
                if (event.key === 'Enter' || event.key === ' ' || event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    this.open = true;
                    this.$nextTick(() => {
                        const selectedIndex = this.findSelectedIndex();
                        this.focusedIndex = selectedIndex >= 0 ? selectedIndex : 0;
                        this.scrollToSelected();
                    });
                }
                return;
            }

            switch(event.key) {
                case 'Escape':
                    event.preventDefault();
                    this.close();
                    if (this.$refs.trigger) {
                        this.$refs.trigger.focus();
                    }
                    break;
                case 'Enter':
                case ' ':
                    event.preventDefault();
                    if (this.focusedIndex >= 0 && this.options[this.focusedIndex]) {
                        this.selectOption(this.options[this.focusedIndex]);
                        if (this.$refs.trigger) {
                            this.$refs.trigger.focus();
                        }
                    }
                    break;
                case 'ArrowDown':
                    event.preventDefault();
                    this.focusedIndex = (this.focusedIndex + 1) % this.options.length;
                    this.$nextTick(() => {
                        const downOption = this.$el.querySelector('[data-option-index=\'' + this.focusedIndex + '\']');
                        if (downOption) downOption.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                    });
                    break;
                case 'ArrowUp':
                    event.preventDefault();
                    this.focusedIndex = this.focusedIndex <= 0 ? this.options.length - 1 : this.focusedIndex - 1;
                    this.$nextTick(() => {
                        const upOption = this.$el.querySelector('[data-option-index=\'' + this.focusedIndex + '\']');
                        if (upOption) upOption.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                    });
                    break;
                case 'Home':
                    event.preventDefault();
                    this.focusedIndex = 0;
                    this.$nextTick(() => {
                        const homeOption = this.$el.querySelector('[data-option-index=\'' + this.focusedIndex + '\']');
                        if (homeOption) homeOption.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                    });
                    break;
                case 'End':
                    event.preventDefault();
                    this.focusedIndex = this.options.length - 1;
                    this.$nextTick(() => {
                        const endOption = this.$el.querySelector('[data-option-index=\'' + this.focusedIndex + '\']');
                        if (endOption) endOption.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                    });
                    break;
            }
        }
    }"
    x-on:keydown="handleKeydown"
    class="relative"
>
    <!-- Label (if provided) -->
    @if($label)
        <label for="{{ $selectId }}" id="{{ $selectId }}_label" class="block {{ $size === 'small' ? 'text-xs' : 'text-sm' }} font-medium text-gray-700 mb-1">
            {{ $label }}
        </label>
    @endif

    <!-- Hidden input for form submission -->
    <input
        type="hidden"
        name="{{ $name }}"
        id="{{ $hiddenInputId }}"
        x-ref="hiddenInput"
        :value="selectedValue"
    >

    <!-- Trigger Button -->
    <button
        type="button"
        x-ref="trigger"
        x-on:click="toggle()"
        :aria-expanded="open"
        aria-haspopup="listbox"
        @if($label) aria-labelledby="{{ $selectId }}_label" @endif
        id="{{ $selectId }}"
        class="relative w-full rounded-md border border-gray-300 bg-white {{ $size === 'small' ? 'py-1.5 pl-2.5 pr-8 text-xs' : 'py-2 pl-3 pr-8 text-sm' }} shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 cursor-pointer"
        :class="{ 'ring-1 ring-indigo-500 border-indigo-500': open }"
    >
        <span class="block truncate" x-text="selectedLabel || placeholder" :class="{ 'text-gray-500': !selectedLabel }"></span>
        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-1.5">
            <svg
                class="h-4 w-4 text-gray-400 transition-transform duration-200"
                :class="{ 'rotate-180': open }"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </span>
    </button>

    <!-- Dropdown Options -->
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        x-on:click.away="close()"
        x-cloak
        class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-xs shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
        role="listbox"
        id="{{ $dropdownId }}"
    >
        <template x-for="(option, index) in options" :key="index">
            <div
                :data-option-index="index"
                x-on:click="selectOption(option)"
                class="relative cursor-pointer select-none py-1.5 pl-2.5 pr-8 hover:bg-indigo-50 focus:bg-indigo-50"
                :class="{
                    'bg-indigo-50': focusedIndex === index,
                    'bg-indigo-100': String(selectedValue) === String(option.value)
                }"
                role="option"
                :aria-selected="String(selectedValue) === String(option.value)"
            >
                <div class="flex items-center">
                    <!-- Option HTML (if provided) -->
                    <template x-if="option.html">
                        <div x-html="option.html" class="flex-1"></div>
                    </template>

                    <!-- Default option display (label with optional icon/badge) -->
                    <template x-if="!option.html">
                        <div class="flex items-center flex-1">
                            <!-- Icon (if provided) -->
                            <template x-if="option.icon">
                                <span class="mr-1.5 flex-shrink-0" x-html="option.icon"></span>
                            </template>

                            <!-- Label -->
                            <span class="block truncate text-xs" x-text="option.label || option.value"></span>

                            <!-- Badge (if provided) -->
                            <template x-if="option.badge">
                                <span class="ml-1.5 flex-shrink-0" x-html="option.badge"></span>
                            </template>
                        </div>
                    </template>

                    <!-- Checkmark for selected option -->
                    <template x-if="String(selectedValue) === String(option.value)">
                        <span class="absolute inset-y-0 right-0 flex items-center pr-2 text-indigo-600">
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </span>
                    </template>
                </div>
            </div>
        </template>

        <!-- Empty state -->
        <template x-if="options.length === 0">
            <div class="relative cursor-default select-none py-1.5 pl-2.5 pr-8 text-gray-500 text-xs">
                No options available
            </div>
        </template>
    </div>
</div>

