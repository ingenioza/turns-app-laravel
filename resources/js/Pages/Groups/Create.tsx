import React from 'react';
import { useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

interface FormData {
    name: string;
    description: string;
    settings: {
        turn_duration?: number;
        notifications_enabled?: boolean;
        auto_advance?: boolean;
    };
}

export default function Create() {
    const { data, setData, post, processing, errors } = useForm<FormData>({
        name: '',
        description: '',
        settings: {
            turn_duration: 30,
            notifications_enabled: true,
            auto_advance: false,
        },
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/groups');
    }

    return (
        <AppLayout title="Create Group">
            <div className="mx-auto max-w-2xl">
                <div className="md:flex md:items-center md:justify-between">
                    <div className="min-w-0 flex-1">
                        <h2 className="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">
                            Create New Group
                        </h2>
                    </div>
                </div>

                <form onSubmit={submit} className="mt-8 space-y-6">
                    <div className="rounded-md bg-white px-6 py-8 shadow">
                        <div className="space-y-6">
                            <div>
                                <label htmlFor="name" className="block text-sm font-medium leading-6 text-gray-900">
                                    Group Name *
                                </label>
                                <div className="mt-2">
                                    <input
                                        type="text"
                                        name="name"
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        className="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                                        placeholder="Enter group name"
                                    />
                                    {errors.name && <p className="mt-2 text-sm text-red-600">{errors.name}</p>}
                                </div>
                            </div>

                            <div>
                                <label htmlFor="description" className="block text-sm font-medium leading-6 text-gray-900">
                                    Description
                                </label>
                                <div className="mt-2">
                                    <textarea
                                        id="description"
                                        name="description"
                                        rows={3}
                                        value={data.description}
                                        onChange={(e) => setData('description', e.target.value)}
                                        className="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                                        placeholder="Optional description for your group"
                                    />
                                    {errors.description && <p className="mt-2 text-sm text-red-600">{errors.description}</p>}
                                </div>
                            </div>

                            <div className="border-t border-gray-200 pt-6">
                                <h3 className="text-lg font-medium leading-6 text-gray-900">Settings</h3>
                                <p className="mt-1 text-sm text-gray-600">Configure how your group operates.</p>

                                <div className="mt-6 space-y-4">
                                    <div>
                                        <label htmlFor="turn_duration" className="block text-sm font-medium leading-6 text-gray-900">
                                            Turn Duration (minutes)
                                        </label>
                                        <div className="mt-2">
                                            <input
                                                type="number"
                                                name="turn_duration"
                                                id="turn_duration"
                                                min="1"
                                                max="1440"
                                                value={data.settings.turn_duration}
                                                onChange={(e) => setData('settings', {
                                                    ...data.settings,
                                                    turn_duration: parseInt(e.target.value)
                                                })}
                                                className="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6"
                                            />
                                        </div>
                                    </div>

                                    <div className="flex items-center">
                                        <input
                                            id="notifications_enabled"
                                            name="notifications_enabled"
                                            type="checkbox"
                                            checked={data.settings.notifications_enabled}
                                            onChange={(e) => setData('settings', {
                                                ...data.settings,
                                                notifications_enabled: e.target.checked
                                            })}
                                            className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                                        />
                                        <div className="ml-3 text-sm leading-6">
                                            <label htmlFor="notifications_enabled" className="font-medium text-gray-900">
                                                Enable notifications
                                            </label>
                                            <p className="text-gray-500">Send notifications when turns are assigned.</p>
                                        </div>
                                    </div>

                                    <div className="flex items-center">
                                        <input
                                            id="auto_advance"
                                            name="auto_advance"
                                            type="checkbox"
                                            checked={data.settings.auto_advance}
                                            onChange={(e) => setData('settings', {
                                                ...data.settings,
                                                auto_advance: e.target.checked
                                            })}
                                            className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                                        />
                                        <div className="ml-3 text-sm leading-6">
                                            <label htmlFor="auto_advance" className="font-medium text-gray-900">
                                                Auto-advance turns
                                            </label>
                                            <p className="text-gray-500">Automatically start the next turn when one is completed.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="flex justify-end space-x-3">
                        <button
                            type="button"
                            onClick={() => window.history.back()}
                            className="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:opacity-50"
                        >
                            {processing ? 'Creating...' : 'Create Group'}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
