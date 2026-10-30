export type User = {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    navigation: {
        users: boolean;
        createUser: boolean;
        products: boolean;
        departments: boolean;
        categories: boolean;
        subcategories: boolean;
        tags: boolean;
    };
};
