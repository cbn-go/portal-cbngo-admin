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

export default function passkeySign() {
    return {
        supported: true,
        signing: false,
        errorMessage: null,

        init() {
            if (!window.PublicKeyCredential) {
                this.supported = false;
            }

            window.addEventListener('init-webauthn-signing', async (event) => {
                await this.handleSigning(event.detail);
            });
        },

        async handleSigning(detail) {
            this.signing = true;
            this.errorMessage = null;

            try {
                const options = detail.options;

                options.challenge = base64UrlToBuffer(options.challenge);

                if (Array.isArray(options.allowCredentials)) {
                    options.allowCredentials = options.allowCredentials.map(cred => ({
                        ...cred,
                        id: base64UrlToBuffer(cred.id)
                    }));
                }

                const assertion = await navigator.credentials.get({
                    publicKey: options
                });

                if (!assertion) {
                    throw new Error('Nenhuma resposta gerada pelo autenticador.');
                }

                const response = assertion.response;

                const payload = {
                    id: assertion.id,
                    rawId: bufferToBase64Url(assertion.rawId),
                    type: assertion.type,
                    response: {
                        clientDataJSON: bufferToBase64Url(response.clientDataJSON),
                        authenticatorData: bufferToBase64Url(response.authenticatorData),
                        signature: bufferToBase64Url(response.signature),
                        userHandle: response.userHandle ? bufferToBase64Url(response.userHandle) : null
                    }
                };

                await this.$wire.finishSigning(payload);
            } catch (err) {
                const isCanceled = err.name === 'NotAllowedError';
                const isNotFound = err.name === 'NotFoundError';

                if (isCanceled) {
                    this.errorMessage = 'Operação de assinatura cancelada pelo usuário ou tempo limite excedido.';
                } else if (isNotFound) {
                    this.errorMessage = 'Nenhuma passkey compatível encontrada neste dispositivo.';
                } else {
                    this.errorMessage = err.message || 'Falha ao assinar relatório no dispositivo.';
                }

                await this.$wire.handleSigningError(this.errorMessage);
            } finally {
                this.signing = false;
            }
        }
    };
}
