export type AdminProcessRunStatus = 'pending' | 'in_progress' | 'completed' | 'failed';

export type AdminProcessStepStatus = 'pending' | 'queued' | 'running' | 'completed' | 'failed';

export interface AdminProcessStep {
    id: number;
    position: number;
    key: string;
    name: string;
    description: string;
    command_line: string;
    is_preview: boolean;
    is_apply: boolean;
    status: AdminProcessStepStatus;
    attempts: number;
    exit_code: number | null;
    started_at: string | null;
    completed_at: string | null;
    duration_seconds: number | null;
    error_message: string | null;
    can_run: boolean;
    can_manage_symbol_changes: boolean;
}

export interface AdminProcessRun {
    id: number;
    process_date: string;
    status: AdminProcessRunStatus;
    started_at: string | null;
    completed_at: string | null;
    error_message: string | null;
    created_by: string | null;
    completed_steps: number;
    total_steps: number;
    steps: AdminProcessStep[];
}

export interface AdminProcessOutputChunk {
    id: number;
    step_id: number;
    stream: 'stdout' | 'stderr';
    output: string;
    created_at: string | null;
}

export interface AdminProcessRunSummary {
    id: number;
    process_date: string;
    status: AdminProcessRunStatus;
    completed_steps: number;
    total_steps: number;
    created_by: string | null;
    created_at: string | null;
}
