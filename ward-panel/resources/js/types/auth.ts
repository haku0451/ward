export type User = {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'viewer';
};

export type Auth = {
    user: User | null;
};
