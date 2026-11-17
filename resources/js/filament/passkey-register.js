function bufferToBase64Url(buffer) {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.byteLength; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}

function base64UrlToBuffer(base64url) {
    let base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
    while (base64.length % 4) {
        base64 += '=';
    }
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
}

export default function passkeyRegister() {
    return {
        supported: true,
        registering: false,
        errorMessage: null,

        init() {
            if (!window.PublicKeyCredential) {
                this.supported = false;
            }

            window.addEventListener('init-webauthn-registration', async (event) => {
                await this.handleRegistration(event.detail);
            });
        },

        async handleRegistration(detail) {
            this.registering = true;
            this.errorMessage = null;

            try {
                const options = detail.options;
                const name = detail.name;

                options.challenge = base64UrlToBuffer(options.challenge);
                options.user.id = base64UrlToBuffer(options.user.id);

                if (Array.isArray(options.excludeCredentials)) {
                    options.excludeCredentials = options.excludeCredentials.map(cred => ({
                        ...cred,
                        id: base64UrlToBuffer(cred.id)
                    }));
                }

                const credential = await navigator.credentials.create({
                    publicKey: options
                });

                if (!credential) {
                    throw new Error('Nenhuma credencial gerada pelo autenticador.');
                }

                const response = credential.response;
                const transports = typeof response.getTransports === 'function' ? response.getTransports() : [];

                const payload = {
                    id: credential.id,
                    rawId: bufferToBase64Url(credential.rawId),
                    type: credential.type,
                    response: {
                        clientDataJSON: bufferToBase64Url(response.clientDataJSON),
                        attestationObject: bufferToBase64Url(response.attestationObject),
                        transports: transports
                    }
                };

                await this.$wire.finishRegistration(name, payload);
            } catch (err) {
                const isCanceled = err.name === 'NotAllowedError';
                this.errorMessage = isCanceled
                    ? 'Operação cancelada ou tempo limite excedido no autenticador.'
                    : (err.message || 'Falha ao registrar credencial no dispositivo.');
                
                await this.$wire.handleRegistrationError(this.errorMessage);
            } finally {
                this.registering = false;
            }
        }
    };
}
