import React, { ReactNode } from 'react';
import { Head, Link } from '@inertiajs/react';

interface AppLayoutProps {
    title?: string;
    children: ReactNode;
}

export default function AppLayout({ title = 'Turns', children }: AppLayoutProps) {
    return (
        <>
            <Head title={title} />
            <div className="min-h-screen bg-gray-50">
                {/* Navigation */}
                <nav className="bg-white shadow">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <div className="flex h-16 justify-between">
                            <div className="flex">
                                <div className="flex flex-shrink-0 items-center">
                                    <Link href="/" className="text-xl font-bold text-gray-900">
                                        Turns
                                    </Link>
                                </div>
                                <div className="hidden sm:ml-6 sm:flex sm:space-x-8">
                                    <Link
                                        href="/groups"
                                        className="inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-medium text-gray-500 hover:border-gray-300 hover:text-gray-700"
                                    >
                                        Groups
                                    </Link>
                                </div>
                            </div>
                            <div className="hidden sm:ml-6 sm:flex sm:items-center">
                                <Link
                                    href="/groups/create"
                                    className="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                                >
                                    New Group
                                </Link>
                            </div>
                        </div>
                    </div>
                </nav>

                {/* Main content */}
                <main className="py-10">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        {children}
                    </div>
                </main>
            </div>
        </>
    );
}
