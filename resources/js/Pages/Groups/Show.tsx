import React from 'react';
import { Link } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout';

interface User {
    id: number;
    name: string;
    email: string;
}

interface Turn {
    id: number;
    status: string;
    started_at: string;
    ended_at?: string;
    duration_formatted?: string;
    user: User;
}

interface Member {
    id: number;
    name: string;
    email: string;
    pivot: {
        role: string;
        turn_order: number;
        joined_at: string;
    };
}

interface Group {
    id: number;
    name: string;
    description?: string;
    invite_code: string;
    status: string;
    created_at: string;
    creator: User;
    active_members: Member[];
    turns: Turn[];
}

interface Props {
    group: Group;
}

export default function Show({ group }: Props) {
    const userRole = group.active_members.find(member => member.id === 1)?.pivot.role || 'member'; // TODO: Get actual current user

    return (
        <AppLayout title={group.name}>
            <div className="lg:flex lg:items-center lg:justify-between">
                <div className="min-w-0 flex-1">
                    <h2 className="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl sm:tracking-tight">
                        {group.name}
                    </h2>
                    {group.description && (
                        <p className="mt-1 text-sm leading-6 text-gray-500">{group.description}</p>
                    )}
                    <div className="mt-1 flex flex-col sm:mt-0 sm:flex-row sm:flex-wrap sm:space-x-6">
                        <div className="mt-2 flex items-center text-sm text-gray-500">
                            <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${
                                group.status === 'active' 
                                    ? 'bg-green-100 text-green-800' 
                                    : 'bg-gray-100 text-gray-800'
                            }`}>
                                {group.status}
                            </span>
                        </div>
                        <div className="mt-2 flex items-center text-sm text-gray-500">
                            <span>{group.active_members.length} members</span>
                        </div>
                        <div className="mt-2 flex items-center text-sm text-gray-500">
                            <span>Created {new Date(group.created_at).toLocaleDateString()}</span>
                        </div>
                    </div>
                </div>
                <div className="mt-5 flex lg:ml-4 lg:mt-0">
                    {userRole === 'admin' && (
                        <span className="sm:ml-3">
                            <Link
                                href={`/groups/${group.id}/edit`}
                                className="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                            >
                                Edit Group
                            </Link>
                        </span>
                    )}
                </div>
            </div>

            <div className="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-3">
                {/* Invite Code */}
                <div className="overflow-hidden rounded-lg bg-white shadow">
                    <div className="p-6">
                        <h3 className="text-base font-semibold leading-6 text-gray-900">Invite Code</h3>
                        <div className="mt-4">
                            <div className="flex items-center space-x-3">
                                <code className="rounded bg-gray-100 px-2 py-1 text-lg font-mono text-gray-900">
                                    {group.invite_code}
                                </code>
                                <button
                                    onClick={() => navigator.clipboard.writeText(group.invite_code)}
                                    className="text-sm text-indigo-600 hover:text-indigo-500"
                                >
                                    Copy
                                </button>
                            </div>
                            <p className="mt-2 text-sm text-gray-500">
                                Share this code with others to invite them to join.
                            </p>
                        </div>
                    </div>
                </div>

                {/* Members */}
                <div className="overflow-hidden rounded-lg bg-white shadow lg:col-span-2">
                    <div className="p-6">
                        <h3 className="text-base font-semibold leading-6 text-gray-900">Members ({group.active_members.length})</h3>
                        <div className="mt-4">
                            <ul role="list" className="divide-y divide-gray-200">
                                {group.active_members
                                    .sort((a, b) => a.pivot.turn_order - b.pivot.turn_order)
                                    .map((member) => (
                                    <li key={member.id} className="py-3">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center">
                                                <div className="flex-shrink-0">
                                                    <div className="h-8 w-8 rounded-full bg-indigo-500 flex items-center justify-center">
                                                        <span className="text-sm font-medium text-white">
                                                            {member.name.charAt(0).toUpperCase()}
                                                        </span>
                                                    </div>
                                                </div>
                                                <div className="ml-3">
                                                    <p className="text-sm font-medium text-gray-900">{member.name}</p>
                                                    <p className="text-sm text-gray-500">Order: #{member.pivot.turn_order}</p>
                                                </div>
                                            </div>
                                            <div className="flex items-center space-x-2">
                                                <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${
                                                    member.pivot.role === 'admin' 
                                                        ? 'bg-purple-100 text-purple-800' 
                                                        : 'bg-gray-100 text-gray-800'
                                                }`}>
                                                    {member.pivot.role}
                                                </span>
                                                {member.id === group.creator.id && (
                                                    <span className="inline-flex rounded-full bg-yellow-100 px-2 text-xs font-semibold leading-5 text-yellow-800">
                                                        Creator
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                </div>

                {/* Recent Turns */}
                <div className="overflow-hidden rounded-lg bg-white shadow lg:col-span-3">
                    <div className="p-6">
                        <div className="flex items-center justify-between">
                            <h3 className="text-base font-semibold leading-6 text-gray-900">Recent Turns</h3>
                            <button className="text-sm text-indigo-600 hover:text-indigo-500">
                                View all
                            </button>
                        </div>
                        {group.turns.length === 0 ? (
                            <div className="mt-4 text-center py-6">
                                <p className="text-sm text-gray-500">No turns yet. Start the first turn!</p>
                                <button className="mt-2 inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                                    Start Turn
                                </button>
                            </div>
                        ) : (
                            <div className="mt-4">
                                <ul role="list" className="divide-y divide-gray-200">
                                    {group.turns.slice(0, 5).map((turn) => (
                                        <li key={turn.id} className="py-3">
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center">
                                                    <div className="flex-shrink-0">
                                                        <div className="h-8 w-8 rounded-full bg-gray-500 flex items-center justify-center">
                                                            <span className="text-sm font-medium text-white">
                                                                {turn.user.name.charAt(0).toUpperCase()}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div className="ml-3">
                                                        <p className="text-sm font-medium text-gray-900">{turn.user.name}</p>
                                                        <p className="text-sm text-gray-500">
                                                            Started {new Date(turn.started_at).toLocaleString()}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="flex items-center space-x-2">
                                                    <span className={`inline-flex rounded-full px-2 text-xs font-semibold leading-5 ${
                                                        turn.status === 'active' 
                                                            ? 'bg-green-100 text-green-800'
                                                            : turn.status === 'completed'
                                                            ? 'bg-blue-100 text-blue-800' 
                                                            : 'bg-gray-100 text-gray-800'
                                                    }`}>
                                                        {turn.status}
                                                    </span>
                                                    {turn.duration_formatted && (
                                                        <span className="text-sm text-gray-500">{turn.duration_formatted}</span>
                                                    )}
                                                </div>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
