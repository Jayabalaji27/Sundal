// components/simple-multi-select.tsx
import React, { useState, useRef, useEffect } from 'react';
import { X } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { createPortal } from 'react-dom';

type Option = {
  value: string;
  label: string;
};

type MultiSelectProps = {
  options: Option[];
  selected: string[];
  onChange: (selected: string[]) => void;
  placeholder?: string;
  className?: string;
};

export function SimpleMultiSelect({
  options,
  selected,
  onChange,
  placeholder = "Select options",
  className,
}: MultiSelectProps) {
  const [isOpen, setIsOpen] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [position, setPosition] = useState({ top: 0, left: 0, width: 0 });
  const [hasInteracted, setHasInteracted] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);
  const dropdownRef = useRef<HTMLDivElement>(null);
  
  // Update position when dropdown opens or on scroll/resize
  useEffect(() => {
    if (!isOpen || !containerRef.current) return;

    const updatePosition = () => {
      if (containerRef.current) {
        const rect = containerRef.current.getBoundingClientRect();
        setPosition({
          top: rect.bottom + 2,
          left: rect.left,
          width: rect.width
        });
      }
    };

    // Small delay to ensure modal is fully rendered
    const timer = setTimeout(updatePosition, 50);
    
    window.addEventListener('scroll', updatePosition, true);
    window.addEventListener('resize', updatePosition);

    return () => {
      clearTimeout(timer);
      window.removeEventListener('scroll', updatePosition, true);
      window.removeEventListener('resize', updatePosition);
    };
  }, [isOpen]);
  
  // Handle click outside to close dropdown
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      const dropdown = dropdownRef.current;
      if (containerRef.current && !containerRef.current.contains(event.target as Node) &&
          dropdown && !dropdown.contains(event.target as Node)) {
        setIsOpen(false);
      }
    }
    
    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
      return () => {
        document.removeEventListener('mousedown', handleClickOutside);
      };
    }
  }, [isOpen]);
  
  // Handle selection
  const handleSelect = (value: string) => {
    if (!selected.includes(value)) {
      onChange([...selected, value]);
    }
    setSearchTerm('');
    setTimeout(() => {
      const input = containerRef.current?.querySelector('input');
      if (input) input.focus();
    }, 10);
  };
  
  // Handle removal
  const handleRemove = (value: string) => {
    onChange(selected.filter(item => item !== value));
  };
  
  // Filter options based on search term
  const filteredOptions = options.filter(option => 
    (searchTerm || !selected.includes(option.value)) && 
    option.label.toLowerCase().includes(searchTerm.toLowerCase())
  );
  
  return (
    <>
      <div ref={containerRef} className={`relative ${className}`}>
        <div 
          className="flex flex-wrap gap-1 p-2 border rounded-md min-h-[38px] cursor-text bg-white"
          onClick={() => {
            setHasInteracted(true);
            setIsOpen(true);
          }}
        >
          {selected.map(value => {
            const option = options.find(o => o.value === value);
            return (
              <Badge key={value} variant="secondary" className="rounded-sm px-1 font-normal">
                {option?.label || value}
                <button
                  type="button"
                  className="ml-1 cursor-pointer rounded-sm"
                  onClick={(e) => {
                    e.stopPropagation();
                    handleRemove(value);
                  }}
                >
                  <X className="h-3 w-3 text-muted-foreground hover:text-foreground" />
                </button>
              </Badge>
            );
          })}
          <input
            type="text"
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            onFocus={() => {
              if (hasInteracted) {
                setIsOpen(true);
              }
            }}
            onClick={() => {
              setHasInteracted(true);
              setIsOpen(true);
            }}
            placeholder={selected.length === 0 ? placeholder : ''}
            className="flex-1 outline-none bg-transparent min-w-[50px]"
          />
        </div>
      </div>
      
      {isOpen && hasInteracted && filteredOptions.length > 0 && createPortal(
        <div
          ref={dropdownRef}
          className="fixed bg-white border rounded-md shadow-lg max-h-[200px] overflow-y-auto z-[9999]"
          style={{
            top: `${position.top}px`,
            left: `${position.left}px`,
            width: `${position.width}px`
          }}
        >
          {filteredOptions.map(option => (
            <div
              key={option.value}
              className="px-3 py-2 cursor-pointer hover:bg-gray-100"
              onClick={() => {
                handleSelect(option.value);
                setIsOpen(true);
              }}
            >
              {option.label}
            </div>
          ))}
        </div>,
        document.body
      )}
    </>
  );
}
