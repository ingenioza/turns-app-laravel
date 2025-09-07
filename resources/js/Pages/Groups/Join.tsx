import React from 'react';
import { useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

interface FormData {
    invite_code: string;
}

export default function Join() {
    const { data, setData, post, processing, errors } = useForm<FormData>({
        invite_code: '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/groups/join');
    }

    return (
        <AppLayout title="Join Group">
            <div className="mx-auto max-w-md">
                <div className="text-center">
                    <h2 className="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:tracking-tight">
                        Join a Group
                    </h2>
                    <p className="mt-2 text-sm text-gray-600">
                        Enter the invite code provided by the group creator.
                    </p>
                </div>

                <form onSubmit={submit} className="mt-8">
                    <div className="rounded-md bg-white px-6 py-8 shadow">
                        <div>
                            <label htmlFor="invite_code" className="block text-sm font-medium leading-6 text-gray-900">
                                Invite Code
                            </label>
                            <div className="mt-2">
                                <input
                                    type="text"
                                    name="invite_code"
                                    id="invite_code"
                                    value={data.invite_code}
                                    onChange={(e) => setData('invite_code', e.target.value.toUpperCase())}
                                    className="block w-full rounded-md border-0 py-1.5 text-center text-lg font-mono text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-xl sm:leading-6"
                                    placeholder="ABC12345"
                                    maxLength={8}
                                />
                                {errors.invite_code && <p className="mt-2 text-sm text-red-600">{errors.invite_code}</p>}
                            </div>
                            <p className="mt-2 text-sm text-gray-500">
                                Invite codes are 8 characters long (letters and numbers).
                            </p>
                        </div>
                    </div>

                    <div className="mt-6 flex justify-end space-x-3">
                        <button
                            type="button"
                            onClick={() => window.history.back()}
                            className="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={processing || data.invite_code.length !== 8}
                            className="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50"
                        >
                            {processing ? 'Joining...' : 'Join Group'}
                        </button>
                    </div>
                </form>

                <div className="mt-8 text-center">
                    <p className="text-sm text-gray-500">
                        Don't have an invite code?{' '}
                        <a href="/groups/create" className="font-medium text-indigo-600 hover:text-indigo-500">
                            Create your own group
                        </a>
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}
