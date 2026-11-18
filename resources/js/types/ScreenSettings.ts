import type { Screen } from '@/types/app/Models/Screen';

export type ScreenSettings = Omit<Screen, 'id' | 'user_id' | 'columns' | 'created_at' | 'updated_at'>;
