import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { UserPlus, User } from 'lucide-react';
import { Task, User as UserType } from '@/types';

interface Props {
    task: Task;
    members: UserType[];
}

export default function TaskAssignment({ task, members }: Props) {
    const [isOpen, setIsOpen] = useState(false);
    const [selectedMember, setSelectedMember] = useState('');

    const handleAssign = () => {
        router.put(route('tasks.update', task.id), {
            assigned_to: selectedMember
        }, {
            onSuccess: () => {
                setIsOpen(false);
                setSelectedMember('');
            }
        });
    };

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <UserPlus className="h-4 w-4 mr-2" />
                    {task.assigned_to ? 'Reassign' : 'Assign'}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Assign Task</DialogTitle>
                </DialogHeader>
                <div className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            Current Assignee
                        </label>
                        <div className="flex items-center space-x-2 p-2 bg-gray-50 rounded">
                            <User className="h-4 w-4 text-gray-400" />
                            <span className="text-sm">
                                {task.assigned_to?.name || 'Unassigned'}
                            </span>
                        </div>
                    </div>
                    
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            Assign to
                        </label>
                        <Select value={selectedMember} onValueChange={setSelectedMember}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select a member" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">Unassigned</SelectItem>
                                {members.map((member) => (
                                    <SelectItem key={member.id} value={member.id.toString()}>
                                        <div className="flex items-center space-x-2">
                                            <User className="h-4 w-4" />
                                            <span>{member.name}</span>
                                        </div>
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="flex justify-end space-x-2">
                        <Button variant="outline" onClick={() => setIsOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={handleAssign}>
                            Assign Task
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}