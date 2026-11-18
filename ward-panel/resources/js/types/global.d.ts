import type { Auth } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            flash: {
                status: string | null;
                plain_token: string | null;
                agent_yaml: string | null;
            };
            [key: string]: unknown;
        };
    }
}
