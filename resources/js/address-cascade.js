/*
| Cascading country → state → city selects for the address forms.
|
| The dataset is split three ways on purpose. Countries (~95 KB) and states
| (~550 KB) load with the form, but the city list is 8 MB, so it is imported
| only once a state has actually been chosen — most edits never touch it.
| Vite turns each dynamic import into its own chunk.
|
| The database stores names ("Philippines", "Metro Manila", "Makati"), not ISO
| codes, so the component keeps the codes to itself and pushes names into the
| Livewire form. When editing an existing record it resolves the stored names
| back to codes so the dependent lists can be populated.
*/

export default function addressCascade({ country = '', state = '', city = '', prefix = 'form' } = {}) {
    return {
        loading: true,
        loadingCities: false,

        countries: [],
        states: [],
        cities: [],

        countryCode: '',
        stateCode: '',

        // Names as stored on the record.
        country,
        state,
        city,

        async init() {
            const [{ default: Country }, { default: State }] = await Promise.all([
                import('country-state-city/lib/country'),
                import('country-state-city/lib/state'),
            ]);

            this.lib = { Country, State };
            this.countries = Country.getAllCountries().map((c) => ({ code: c.isoCode, name: c.name }));

            // Editing: resolve the stored names back to codes.
            if (this.country) {
                this.countryCode = this.countries.find((c) => c.name === this.country)?.code ?? '';
                this.loadStates();
            }

            if (this.state) {
                this.stateCode = this.states.find((s) => s.name === this.state)?.code ?? '';
                await this.loadCities();
            }

            this.loading = false;
        },

        loadStates() {
            this.states = this.countryCode
                ? this.lib.State.getStatesOfCountry(this.countryCode).map((s) => ({ code: s.isoCode, name: s.name }))
                : [];
        },

        /** Pulls the city dataset in on first use, then keeps it. */
        async loadCities() {
            if (! this.countryCode || ! this.stateCode) {
                this.cities = [];

                return;
            }

            if (! this.lib.City) {
                this.loadingCities = true;
                this.lib.City = (await import('country-state-city/lib/city')).default;
                this.loadingCities = false;
            }

            this.cities = this.lib.City
                .getCitiesOfState(this.countryCode, this.stateCode)
                .map((c) => ({ name: c.name }));
        },

        /** Changing the country invalidates everything below it. */
        onCountry() {
            this.country = this.countries.find((c) => c.code === this.countryCode)?.name ?? '';
            this.stateCode = '';
            this.state = '';
            this.city = '';
            this.cities = [];

            this.loadStates();

            this.push({ country: this.country, state_province: '', city: '' });
        },

        async onState() {
            this.state = this.states.find((s) => s.code === this.stateCode)?.name ?? '';
            this.city = '';

            this.push({ state_province: this.state, city: '' });

            await this.loadCities();
        },

        onCity() {
            this.push({ city: this.city });
        },

        /** Write the resolved names onto the Livewire form object. */
        push(values) {
            Object.entries(values).forEach(([field, value]) => {
                this.$wire.set(`${prefix}.${field}`, value, false);
            });
        },
    };
}
