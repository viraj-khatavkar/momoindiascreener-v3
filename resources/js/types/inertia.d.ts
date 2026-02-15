import type { User } from './app/Models/User';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            flash: {
                success: string | null;
                error: string | null;
                warning: string | null;
            };
            auth: {
                user: User | null;
            };
        };
    }
}
