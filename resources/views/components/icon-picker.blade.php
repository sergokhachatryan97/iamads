@props([
    'name' => 'icon',
    'value' => '',
    'label' => '',
])

<div x-data="iconPickerComponent('{{ $name }}', @js($value))"
     class="relative z-10"
     @keydown.escape.window="close()">

    <input type="hidden" name="{{ $name }}" id="{{ $name }}" :value="selectedValue">

    <!-- Trigger Button -->
    <div class="relative">
        <button type="button"
                x-ref="trigger"
                @click.stop="toggle()"
                @keydown.enter.prevent="toggle()"
                @keydown.space.prevent="toggle()"
                :aria-expanded="isOpen"
                aria-haspopup="true"
                aria-label="Select icon"
                class="w-10 h-10 rounded border border-gray-300 bg-white flex items-center justify-center hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 transition-all">
            <template x-if="selectedValue && selectedValue.includes('fa-')">
                <i :class="selectedValue" class="text-gray-800"></i>
            </template>

            <template x-if="selectedValue && !selectedValue.includes('fa-') && !selectedValue.startsWith('data:')">
                <span class="text-xl leading-none" x-text="selectedValue"></span>
            </template>

            <template x-if="selectedValue && selectedValue.startsWith('data:')">
                <img :src="selectedValue" alt="Icon" class="w-6 h-6 object-contain rounded">
            </template>

            <template x-if="!selectedValue">
                <span class="text-gray-400 text-xl leading-none">😊</span>
            </template>
        </button>

        <!-- Clear Selection Button -->
        <button type="button"
                x-show="selectedValue"
                @click.stop="clearSelection()"
                @keydown.enter.prevent="clearSelection()"
                class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center text-[8px] focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1 z-10"
                aria-label="Clear selection"
                title="Clear selection">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Popover (Teleported to body to avoid modal clipping/stacking issues) -->
    <template x-teleport="body">
        <div x-show="isOpen"
             x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 translate-y-1"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-1"
             @click.stop
             @click.away="close()"
             x-ref="popover"
             class="fixed z-[99999]  bg-white rounded-lg shadow-xl border border-gray-200 overflow-hidden"
             :style="popoverStyle"
             role="dialog"
             aria-modal="true"
             aria-label="Icon picker">

            <!-- Header with Tabs -->
            <div class="border-b border-gray-200 bg-white">
                <div role="tablist" class="flex">
                    <button
                        type="button"
                        @click="switchTab('icons')"
                        :aria-selected="activeTab === 'icons'"
                        :class="activeTab === 'icons' ? 'border-b-2 border-gray-900 text-gray-900 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                        role="tab"
                        aria-controls="icons-panel"
                        id="icons-tab"
                        class="flex-1 px-4 py-3 text-sm font-medium flex items-center justify-center gap-2 transition-colors"
                    >
                        <i class="fas fa-icons text-sm"></i>
                        <span>{{ __('Icons') }}</span>
                    </button>

                    <button
                        type="button"
                        @click="switchTab('emoji')"
                        :aria-selected="activeTab === 'emoji'"
                        :class="activeTab === 'emoji' ? 'border-b-2 border-gray-900 text-gray-900 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                        role="tab"
                        aria-controls="emoji-panel"
                        id="emoji-tab"
                        class="flex-1 px-4 py-3 text-sm font-medium flex items-center justify-center gap-2 transition-colors"
                    >
                        <span class="text-sm">😀</span>
                        <span>{{ __('Emoji') }}</span>
                    </button>

                    <button
                        type="button"
                        @click="switchTab('uploaded')"
                        :aria-selected="activeTab === 'uploaded'"
                        :class="activeTab === 'uploaded' ? 'border-b-2 border-gray-900 text-gray-900 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                        role="tab"
                        aria-controls="uploaded-panel"
                        id="uploaded-tab"
                        class="flex-1 px-4 py-3 text-sm font-medium flex items-center justify-center gap-2 transition-colors"
                    >
                        <i class="fas fa-upload text-sm"></i>
                        <span>{{ __('Uploaded') }}</span>
                    </button>
                </div>
            </div>

            <!-- Search Input -->
            <div class="p-4 border-b border-gray-200" x-show="activeTab !== 'uploaded'">
                <input type="text"
                       x-model.debounce.200ms="searchQuery"
                       placeholder="{{ __('Search...') }}"
                       x-ref="searchInput"
                       class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                       aria-label="Search">
            </div>

             <!-- Content Panels -->
             <div class="overflow-y-auto custom-scrollbar" style="max-height: 320px; min-height: 300px;">
                <div class="p-4">

                    <!-- Loading State -->
                    <div x-show="loading" class="text-center py-12 text-sm text-gray-500">
                        <i class="fas fa-spinner fa-spin mr-2"></i> {{ __('Loading...') }}
                    </div>

                    <!-- Icons Panel -->
                    <div x-show="!loading && activeTab === 'icons'" role="tabpanel" id="icons-panel" aria-labelledby="icons-tab">
                        <div x-show="filteredIcons.length > 0" class="grid grid-cols-8 gap-2">
                            <template x-for="(icon, index) in filteredIcons" :key="`icon-${index}-${icon.class}`">
                                <button type="button"
                                        @click="select(icon.class)"
                                        @keydown.enter.prevent="select(icon.class)"
                                        :aria-selected="selectedValue === icon.class"
                                        :title="icon.name"
                                        class="aspect-square p-2 rounded hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 transition-colors flex items-center justify-center group">
                                    <i :class="icon.class" class="text-gray-700 group-hover:text-gray-900 text-sm"></i>
                                </button>
                            </template>
                        </div>

                        <div x-show="filteredIcons.length === 0" class="text-center py-12 text-sm text-gray-400">
                            {{ __('No results found') }}
                        </div>
                    </div>

                    <!-- Emoji Panel -->
                    <div x-show="!loading && activeTab === 'emoji'"
                         role="tabpanel"
                         id="emoji-panel"
                         aria-labelledby="emoji-tab">
                        <div x-show="filteredEmojis.length > 0" class="grid grid-cols-8 gap-2">
                            <template x-for="(emoji, index) in filteredEmojis" :key="`emoji-${index}-${emoji.symbol}`">
                                <button type="button"
                                        @click="select(emoji.symbol)"
                                        @keydown.enter.prevent="select(emoji.symbol)"
                                        :aria-selected="selectedValue === emoji.symbol"
                                        :title="emoji.name"
                                        class="aspect-square p-2 rounded hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 transition-colors flex items-center justify-center text-lg">
                                    <span x-text="emoji.symbol"></span>
                                </button>
                            </template>
                        </div>

                        <div x-show="filteredEmojis.length === 0" class="text-center py-12 text-sm text-gray-400">
                            {{ __('No results found') }}
                        </div>
                    </div>

                    <!-- Uploaded Panel -->
                    <div x-show="!loading && activeTab === 'uploaded'"
                         role="tabpanel"
                         id="uploaded-panel"
                         aria-labelledby="uploaded-tab">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ __('Upload Image') }}
                            </label>
                            <input type="file"
                                   @change="handleFileUpload($event)"
                                   accept="image/*"
                                   class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                        </div>

                        <div class="grid grid-cols-6 gap-2" x-show="uploadedImages.length > 0">
                            <template x-for="(image, index) in uploadedImages" :key="index">
                                <div class="relative">
                                    <button type="button"
                                            @click="select(image.url)"
                                            @keydown.enter.prevent="select(image.url)"
                                            :aria-selected="selectedValue === image.url"
                                            :title="image.name"
                                            class="aspect-square rounded border-2 border-gray-300 overflow-hidden hover:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors w-full">
                                        <img :src="image.url" :alt="image.name" class="w-full h-full object-cover">
                                    </button>
                                    <button type="button"
                                            @click.stop="removeUploadedImage(index)"
                                            @keydown.enter.prevent="removeUploadedImage(index)"
                                            class="absolute top-0 left-0 w-4 h-4 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center text-[8px] shadow-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1 z-20"
                                            aria-label="Remove image"
                                            title="Remove image">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </template>
                        </div>

                        <div x-show="uploadedImages.length === 0" class="text-center py-12 text-sm text-gray-400">
                            {{ __('No uploaded images') }}
                        </div>
                    </div>

                 </div>
             </div>
         </div>
     </template>
 </div>

<script>
    function iconPickerComponent(inputName, initialValue) {
        return {
            isOpen: false,
            activeTab: 'icons',
            searchQuery: '',
            selectedValue: initialValue || '',
            iconName: inputName,

            icons: [],
            emojis: [],
            uploadedImages: [],

            loading: false,
            dataLoaded: false,

            popoverStyle: 'top:175px; left:35%; width:405px; height: 300px',

            _repositionHandler: null,

            init() {
                // If you want: preload nothing. We'll load only when opened.
            },

            async toggle() {
                this.isOpen = !this.isOpen;

                if (this.isOpen && !this.dataLoaded) {
                    await this.loadData();
                }

                if (this.isOpen) {
                    this.$nextTick(() => {
                        this.positionPopover();
                        this.bindReposition();
                        this.focusSearch();
                    });
                } else {
                    this.unbindReposition();
                    this.searchQuery = '';
                }
            },

            close() {
                this.isOpen = false;
                this.searchQuery = '';
                this.unbindReposition();
            },

            focusSearch() {
                if (this.activeTab !== 'uploaded' && this.$refs.searchInput) {
                    this.$refs.searchInput.focus();
                }
            },

            bindReposition() {
                if (this._repositionHandler) return;

                this._repositionHandler = () => {
                    if (this.isOpen) this.positionPopover();
                };

                window.addEventListener('scroll', this._repositionHandler, true);
                window.addEventListener('resize', this._repositionHandler);
            },

            unbindReposition() {
                if (!this._repositionHandler) return;
                window.removeEventListener('scroll', this._repositionHandler, true);
                window.removeEventListener('resize', this._repositionHandler);
                this._repositionHandler = null;
            },

            positionPopover() {
                const btn = this.$refs.trigger;
                const pop = this.$refs.popover;
                if (!btn || !pop) return;

                const prevDisplay = pop.style.display;
                const prevVis = pop.style.visibility;

                pop.style.visibility = 'hidden';
                pop.style.display = 'block';

                const popW = pop.offsetWidth || 405;
                const pad = 16;

                // Find the modal/popup container (parent modal)
                let modal = btn.closest('.fixed, [role="dialog"]');
                if (!modal) {
                    // Fallback: find any parent with fixed/absolute positioning
                    modal = btn.closest('[class*="fixed"], [class*="absolute"]');
                }

                let left;
                const top = 175; // Fixed top position

                if (modal) {
                    // Center relative to modal
                    const modalRect = modal.getBoundingClientRect();
                    const modalCenterX = modalRect.left + (modalRect.width / 2);
                    left = modalCenterX - (popW / 2);

                    // Ensure popover stays within modal horizontally
                    if (left < modalRect.left + pad) {
                        left = modalRect.left + pad;
                    } else if (left + popW > modalRect.right - pad) {
                        left = modalRect.right - popW - pad;
                    }
                } else {
                    // Fallback: center relative to viewport
                    left = (window.innerWidth / 2) - (popW / 2);

                    // Ensure popover stays within viewport
                    if (left < pad) {
                        left = pad;
                    } else if (left + popW > window.innerWidth - pad) {
                        left = window.innerWidth - popW - pad;
                    }
                }

                this.popoverStyle = `top:${top}px;left:${Math.round(left)}px;width:${popW}px;`;

                pop.style.visibility = prevVis;
                pop.style.display = prevDisplay;
            },

            switchTab(tab) {
                this.activeTab = tab;
                this.searchQuery = '';

                if (this.isOpen) {
                    this.$nextTick(() => {
                        this.positionPopover();
                        this.focusSearch();
                    });
                }
            },

            async loadData() {
                this.loading = true;

                try {
                    await Promise.all([this.loadIcons(), this.loadEmojis()]);
                    this.dataLoaded = true;
                } catch (e) {
                    console.error('Icon picker loadData error:', e);
                } finally {
                    this.loading = false;
                }
            },

            async loadIcons() {
                try {
                    const response = await fetch('https://raw.githubusercontent.com/FortAwesome/Font-Awesome/master/metadata/icons.json');
                    if (!response.ok) throw new Error('Failed to fetch icons.json');

                    const data = await response.json();
                    const allIcons = [];

                    Object.keys(data).forEach(key => {
                        const iconData = data[key];
                        const styles = iconData.styles || [];
                        const label = iconData.label || key.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase());

                        if (styles.includes('solid')) {
                            allIcons.push({ name: label, class: `fas fa-${key}` });
                        }
                        if (styles.includes('regular')) {
                            allIcons.push({ name: `${label} (Regular)`, class: `far fa-${key}` });
                        }
                        if (styles.includes('brands')) {
                            allIcons.push({ name: label, class: `fab fa-${key}` });
                        }
                    });

                    allIcons.sort((a, b) => a.name.localeCompare(b.name));
                    this.icons = allIcons;
                } catch (error) {
                    console.error('Error loading icons, using fallback:', error);
                    this.icons = [
                        {name: 'Home', class: 'fas fa-home'},
                        {name: 'User', class: 'fas fa-user'},
                        {name: 'Settings', class: 'fas fa-cog'},
                        {name: 'Search', class: 'fas fa-search'},
                        {name: 'Upload', class: 'fas fa-upload'},
                        {name: 'Facebook', class: 'fab fa-facebook'},
                        {name: 'Instagram', class: 'fab fa-instagram'},
                    ];
                }
            },

            async loadEmojis() {
                try {
                    const response = await fetch('https://emojihub.yurace.pro/api/all');
                    if (!response.ok) throw new Error('Failed to fetch emojis');

                    const data = await response.json();
                    this.emojis = data.map(e => {
                        let symbol = '';

                        if (Array.isArray(e.unicode) && e.unicode.length) {
                            try {
                                const cps = e.unicode
                                    .map(x => (typeof x === 'string' ? parseInt(x.replace(/^U\+/i, '').trim(), 16) : x))
                                    .filter(n => Number.isFinite(n) && n > 0 && n <= 0x10FFFF);
                                if (cps.length) symbol = String.fromCodePoint(...cps);
                            } catch {}
                        }

                        if (!symbol && e.htmlCode) {
                            try {
                                let hc = Array.isArray(e.htmlCode) ? e.htmlCode[0] : e.htmlCode;
                                if (typeof hc === 'string') {
                                    const num = parseInt(hc.replace(/&#/g, '').replace(/;/g, ''), 10);
                                    if (Number.isFinite(num) && num > 0 && num <= 0x10FFFF) symbol = String.fromCodePoint(num);
                                }
                            } catch {}
                        }

                        return { name: e.name || 'Emoji', symbol };
                    }).filter(x => x.symbol);
                } catch (error) {
                    console.error('Error loading emojis, using fallback:', error);
                    this.emojis = [
                        {name: 'Grinning Face', symbol: '😀'},
                        {name: 'Smiling Face', symbol: '😊'},
                        {name: 'Heart', symbol: '❤️'},
                        {name: 'Fire', symbol: '🔥'},
                    ];
                }
            },

            get filteredIcons() {
                const q = (this.searchQuery || '').trim().toLowerCase();
                if (!q) return this.icons;
                return this.icons.filter(i => i?.name?.toLowerCase().includes(q));
            },

            get filteredEmojis() {
                const q = (this.searchQuery || '').trim().toLowerCase();
                if (!q) return this.emojis;
                return this.emojis.filter(e => e?.name?.toLowerCase().includes(q));
            },

            select(value) {
                this.selectedValue = value;

                const hiddenInput = document.getElementById(this.iconName);
                if (hiddenInput) hiddenInput.value = value;
            },

            handleFileUpload(event) {
                const file = event.target.files?.[0];
                if (!file) return;

                const reader = new FileReader();
                reader.onload = (e) => {
                    const imageData = e.target.result;
                    const imageObj = { name: file.name, url: imageData };
                    this.uploadedImages.push(imageObj);
                    this.select(imageData);
                };
                reader.readAsDataURL(file);
            },

            removeUploadedImage(index) {
                const removedImage = this.uploadedImages[index];

                // Remove from array
                this.uploadedImages.splice(index, 1);

                // If the removed image was selected, clear selection
                if (this.selectedValue === removedImage.url) {
                    this.clearSelection();
                }
            },

            clearSelection() {
                this.selectedValue = '';
                const hiddenInput = document.getElementById(this.iconName);
                if (hiddenInput) {
                    hiddenInput.value = '';
                }
            }
        };
    }
</script>
