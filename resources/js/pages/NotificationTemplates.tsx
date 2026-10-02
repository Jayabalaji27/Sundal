import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import { Search, Eye } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

const templates = [
  { id: 1, name: 'Low Stock' },
  { id: 2, name: 'New Customer' },
  { id: 3, name: 'New Purchase' },
  { id: 4, name: 'New Quotation' },
  { id: 5, name: 'New Return' },
  { id: 6, name: 'New Sale' },
  { id: 7, name: 'New Sales' },
  { id: 8, name: 'New Vendor' },
  { id: 9, name: 'Order Complete' },
  { id: 10, name: 'Payment Due' },
];

export default function NotificationTemplates() {
  const [searchTerm, setSearchTerm] = useState('');

  const filteredTemplates = templates.filter(template =>
    template.name.toLowerCase().includes(searchTerm.toLowerCase())
  );
  
  return (
    <>
      <Head title="Notification Templates" />
      <div className="p-6">
        <div className="mb-6">
          <h1 className="text-2xl font-bold mb-4">Notification Templates</h1>
          
          <div className="flex items-center gap-4 mb-6">
            <div className="relative flex-1 max-w-sm">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4" />
              <Input
                placeholder="Search..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="pl-10"
              />
            </div>
            <Button>Search</Button>
          </div>
        </div>

        <Card>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Name</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {filteredTemplates.map((template) => (
                  <TableRow key={template.id}>
                    <TableCell className="font-medium">{template.name}</TableCell>
                    <TableCell className="text-right">
                      <Button variant="ghost" size="sm">
                        <Eye className="h-4 w-4" />
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>

        <div className="flex items-center justify-between mt-4">
          <p className="text-sm text-gray-600">
            Showing 1 to {filteredTemplates.length} of {templates.length} templates
          </p>
          <div className="flex items-center gap-2">
            <Button variant="outline" size="sm">Previous</Button>
            <Button variant="default" size="sm">1</Button>
            <Button variant="outline" size="sm">2</Button>
            <Button variant="outline" size="sm">Next</Button>
          </div>
        </div>
      </div>
    </>
  );
}