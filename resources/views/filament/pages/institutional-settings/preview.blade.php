<div class="rounded-xl border border-gray-200 bg-gray-50 p-6 dark:border-gray-800 dark:bg-gray-900/60">
    <div class="mb-4 flex items-center justify-between border-b border-gray-200 pb-3 dark:border-gray-800">
        <div>
            <span class="text-xs font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400">Simulação do Rodapé</span>
            <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Como estas informações aparecerão no portal público</h4>
        </div>
        <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
            Tempo Real
        </span>
    </div>

    @php
        $formData = $this->data ?? [];
        $email = !empty($formData['email']) ? $formData['email'] : ($setting->email ?? 'contato@cbngo.org.br');
        $phone = $formData['phone'] ?? $setting->phone ?? null;
        $whatsapp = $formData['whatsapp'] ?? $setting->whatsapp ?? null;
        $hours = $formData['office_hours'] ?? $setting->office_hours ?? null;
        
        $street = $formData['street'] ?? $setting->street ?? '';
        $number = $formData['number'] ?? $setting->number ?? '';
        $complement = $formData['complement'] ?? $setting->complement ?? '';
        $district = $formData['district'] ?? $setting->district ?? '';
        $city = $formData['city'] ?? $setting->city ?? 'Goiânia';
        $state = $formData['state'] ?? $setting->state ?? 'GO';
        $cep = $formData['postal_code'] ?? $setting->postal_code ?? '';

        $addressParts = [];
        if ($street) {
            $st = $street;
            if ($number) $st .= ', ' . $number;
            if ($complement) $st .= ' (' . $complement . ')';
            $addressParts[] = $st;
        }
        if ($district) $addressParts[] = $district;
        if ($city) $addressParts[] = $city . '/' . $state;
        if ($cep) $addressParts[] = 'CEP ' . $cep;
        $formattedAddress = implode(' - ', $addressParts);

        $socials = [
            'instagram' => $formData['instagram_url'] ?? $setting->instagram_url ?? null,
            'facebook' => $formData['facebook_url'] ?? $setting->facebook_url ?? null,
            'youtube' => $formData['youtube_url'] ?? $setting->youtube_url ?? null,
            'tiktok' => $formData['tiktok_url'] ?? $setting->tiktok_url ?? null,
            'spotify' => $formData['spotify_url'] ?? $setting->spotify_url ?? null,
            'linkedin' => $formData['linkedin_url'] ?? $setting->linkedin_url ?? null,
        ];
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-xs text-gray-600 dark:text-gray-300">
        {{-- Contatos --}}
        <div class="space-y-2">
            <h5 class="font-bold uppercase tracking-wider text-gray-900 dark:text-white">Fale Conosco</h5>
            <div class="flex items-center gap-2">
                <span class="font-semibold text-gray-700 dark:text-gray-200">E-mail:</span>
                <span>{{ $email ?: 'Não informado' }}</span>
            </div>
            @if($phone)
                <div class="flex items-center gap-2">
                    <span class="font-semibold text-gray-700 dark:text-gray-200">Telefone:</span>
                    <span>{{ $phone }}</span>
                </div>
            @endif
            @if($whatsapp)
                <div class="flex items-center gap-2">
                    <span class="font-semibold text-emerald-600 dark:text-emerald-400">WhatsApp:</span>
                    <span class="font-medium text-gray-800 dark:text-gray-100">{{ $whatsapp }}</span>
                </div>
            @endif
            @if($hours)
                <div class="flex items-center gap-2">
                    <span class="font-semibold text-gray-700 dark:text-gray-200">Atendimento:</span>
                    <span>{{ $hours }}</span>
                </div>
            @endif
        </div>

        {{-- Sede / Endereço --}}
        <div class="space-y-2">
            <h5 class="font-bold uppercase tracking-wider text-gray-900 dark:text-white">Sede Administrativa</h5>
            <p class="leading-relaxed">
                {{ $formattedAddress ?: 'Endereço ainda não preenchido' }}
            </p>
            @if(!empty($formData['maps_url']))
                <a href="{{ $formData['maps_url'] }}" target="_blank" class="inline-flex items-center gap-1 font-semibold text-primary-600 hover:underline dark:text-primary-400">
                    Ver no Google Maps &rarr;
                </a>
            @endif
        </div>

        {{-- Redes Sociais --}}
        <div class="space-y-2">
            <h5 class="font-bold uppercase tracking-wider text-gray-900 dark:text-white">Redes Sociais Oficiais</h5>
            <p class="text-[11px] text-gray-500">Ícones visíveis no rodapé do portal público:</p>
            <div class="flex flex-wrap gap-2 pt-1">
                @php $hasSocial = false; @endphp
                @foreach($socials as $network => $url)
                    @if(!empty($url))
                        @php $hasSocial = true; @endphp
                        <span class="inline-flex items-center px-2 py-1 rounded bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-[11px] font-medium text-gray-700 dark:text-gray-200 capitalize">
                            {{ $network }}
                        </span>
                    @endif
                @endforeach
                @if(!$hasSocial)
                    <span class="text-[11px] italic text-gray-400">Nenhuma rede social configurada</span>
                @endif
            </div>
        </div>
    </div>
</div>
