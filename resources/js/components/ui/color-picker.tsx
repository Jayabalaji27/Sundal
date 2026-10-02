import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface ColorPickerProps {
    value: string;
    onChange: (color: string) => void;
    presetColors?: string[];
    className?: string;
}

const defaultPresetColors = [
    '#3B82F6', // Blue
    '#EF4444', // Red  
    '#10B981', // Green
    '#F59E0B', // Yellow
    '#8B5CF6', // Purple
    '#EC4899', // Pink
    '#6B7280', // Gray
    '#84CC16', // Lime
    '#F97316', // Orange
    '#06B6D4', // Cyan
    '#DC2626', // Red-600
    '#059669', // Green-600
    '#7C3AED', // Violet-600
    '#DB2777', // Pink-600
    '#4F46E5', // Indigo-600
    '#0891B2'  // Cyan-600
];

export function ColorPicker({ 
    value, 
    onChange, 
    presetColors = defaultPresetColors,
    className = ""
}: ColorPickerProps) {
    const [isOpen, setIsOpen] = useState(false);    
    const [customColor, setCustomColor] = useState(value);

    const handlePresetColorClick = (color: string) => {
        onChange(color);
        setCustomColor(color);
        setIsOpen(false);
    };

    const handleCustomColorChange = (color: string) => {
        setCustomColor(color);
        onChange(color);
    };

    return (
        <Popover open={isOpen} onOpenChange={setIsOpen}>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    className={`w-full justify-start text-left font-normal ${className}`}
                    onClick={() => setIsOpen(true)}
                >
                    <div className="flex items-center gap-2">
                        <div 
                            className="w-4 h-4 rounded border border-gray-300" 
                            style={{ backgroundColor: value }}
                        />
                        <span className="text-xs font-mono">{value}</span>
                    </div>
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-64">
                <div className="space-y-3">
                    <div>
                        <Label className="text-sm font-medium">Preset Colors</Label>
                        <div className="grid grid-cols-8 gap-2 mt-2">
                            {presetColors.map((color) => (
                                <button
                                    key={color}
                                    type="button"
                                    className={`w-6 h-6 rounded border-2 hover:scale-110 transition-transform ${
                                        value === color ? 'border-gray-900' : 'border-gray-300'
                                    }`}
                                    style={{ backgroundColor: color }}
                                    onClick={() => handlePresetColorClick(color)}
                                    title={color}
                                />
                            ))}
                        </div>
                    </div>
                    
                    <div>
                        <Label className="text-sm font-medium">Custom Color</Label>
                        <div className="flex gap-2 mt-2">
                            <input
                                type="color"
                                value={customColor}
                                onChange={(e) => handleCustomColorChange(e.target.value)}
                                className="w-10 h-8 rounded border cursor-pointer"
                            />
                            <Input
                                type="text"
                                value={customColor}
                                onChange={(e) => handleCustomColorChange(e.target.value)}
                                placeholder="#000000"
                                className="flex-1 text-xs font-mono"
                            />
                        </div>
                    </div>
                </div>
            </PopoverContent>
        </Popover>
    );
}