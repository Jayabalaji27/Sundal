// components/multi-select-field.tsx
import React, { useState } from 'react';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { X } from 'lucide-react';
import { FormField } from '@/types/crud';

interface MultiSelectFieldProps {
  field: FormField;
  formData: Record<string, any>;
  handleChange: (name: string, value: any) => void;
}

export function MultiSelectField({ field, formData, handleChange }: MultiSelectFieldProps) {
  // Ensure selected value is always an array of strings
  const selectedValues = Array.isArray(formData[field.name]) 
    ? formData[field.name] 
    : formData[field.name] 
      ? [formData[field.name].toString()] 
      : [];
  
  const [search, setSearch] = useState('');

  const filteredOptions = (field.options || []).filter(option =>
    option.label.toLowerCase().includes(search.toLowerCase())
  );

  return (
    <>
      <Select
        value=""
        onValueChange={(value) => {
          if (value && !selectedValues.includes(value)) {
            handleChange(field.name, [...selectedValues, value]);
          }
          setSearch('');
        }}
      >
        <SelectTrigger className="bg-white">
          <SelectValue placeholder={field.placeholder || `Select ${field.label}`} />
        </SelectTrigger>
        <SelectContent className="bg-white border shadow-lg z-[9999]">
          <div className="px-2 py-1.5">
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              onKeyDown={(e) => e.stopPropagation()}
              placeholder="Search..."
              className="w-full rounded-sm border border-input px-2 py-1 text-sm outline-none"
              autoFocus
            />
          </div>
          {filteredOptions.map((option) => (
            <SelectItem
              key={option.value}
              value={option.value}
              className="bg-white hover:bg-gray-100"
              disabled={selectedValues.includes(option.value)}
            >
              {option.label}
            </SelectItem>
          ))}
          {filteredOptions.length === 0 && (
            <div className="px-3 py-2 text-sm text-muted-foreground">No results found.</div>
          )}
        </SelectContent>
      </Select>

      {selectedValues.length > 0 && (
        <div className="mt-2 flex flex-wrap gap-2">
          {selectedValues.map((value) => {
            const option = (field.options || []).find(o => o.value === value);
            return option ? (
              <Badge key={value} variant="secondary" className="flex items-center space-x-1">
                <span>{option.label}</span>
                <button
                  type="button"
                  onClick={() => {
                    handleChange(field.name, selectedValues.filter(id => id !== value));
                  }}
                  className="ml-1 cursor-pointer hover:text-red-600"
                >
                  <X className="h-3 w-3" />
                </button>
              </Badge>
            ) : null;
          })}
        </div>
      )}
    </>
  );
}