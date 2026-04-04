export interface Blog {
    id: number;
    title: string;
    slug: string;
    content: string;
    excerpt: string | null;
    featured_image: string | null;
    is_published: boolean;
    published_at: string | null;
    is_paid: boolean;
    created_at: string;
    updated_at: string;
}
