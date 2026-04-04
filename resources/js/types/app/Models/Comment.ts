export interface Comment {
    id: number;
    blog_id: number;
    user_id: number;
    parent_id: number | null;
    body: string;
    created_at: string;
    updated_at: string;
    user: {
        id: number;
        name: string;
    };
    replies: Comment[];
}
