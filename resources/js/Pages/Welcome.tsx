import { Head } from '@inertiajs/react';

export default function Welcome({ appName }: { appName: string }) {
    return (
        <>
            <Head title="Welcome" />
            
            <div className="min-h-screen bg-gray-50 flex items-center justify-center">
                <div className="max-w-md mx-auto text-center">
                    <h1 className="text-3xl font-bold text-gray-900 mb-4">
                        Welcome to {appName}
                    </h1>
                    <p className="text-gray-600 mb-8">
                        This is your Inertia React application running on Laravel 12.
                    </p>
                    <div className="space-y-4">
                        <div className="bg-white p-4 rounded-lg shadow-sm">
                            <h2 className="font-semibold text-gray-900">Features</h2>
                            <ul className="mt-2 text-sm text-gray-600">
                                <li>✅ Laravel 12</li>
                                <li>✅ Inertia.js v2</li>
                                <li>✅ React 18</li>
                                <li>✅ TypeScript</li>
                                <li>✅ Tailwind CSS</li>
                                <li>✅ Pest Testing</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
