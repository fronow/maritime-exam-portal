
import React, { useState, useEffect } from 'react';
import { useLanguage } from '../App';
import { TRANSLATIONS } from '../constants';
import { 
    fetchAdminData, fetchInitialData, saveCategory, savePackage, 
    requestAccess, approveRequest, toggleSuspendUser, importQuestionsToBackend, saveSettings 
} from '../services/storageService';
import { parseExcel } from '../services/excelService';
import { User, Category, Package, GlobalSettings } from '../types';
import { 
    Users, Book, Tag, Check, Lock, Unlock, Upload, Plus, Trash2, Edit2, 
    Package as PackageIcon, Settings as SettingsIcon, Save, Calendar, Link, Megaphone 
} from 'lucide-react';

const AdminPanel: React.FC = () => {
  const { lang } = useLanguage();
  const t = TRANSLATIONS[lang];
  const [activeTab, setActiveTab] = useState<'users' | 'cats' | 'pkgs' | 'reqs' | 'settings'>('reqs');
  
  const [users, setUsers] = useState<User[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [packages, setPackages] = useState<Package[]>([]);
  const [settings, setSettings] = useState<GlobalSettings>({ revolutLink: '', facebookLink: '', announcement: '' });
  const [importStatus, setImportStatus] = useState<Record<string, string>>({});

  const [editingCat, setEditingCat] = useState<Category | null>(null);
  const [isNewCat, setIsNewCat] = useState(false);
  const [editingPkg, setEditingPkg] = useState<Package | null>(null);
  const [isNewPkg, setIsNewPkg] = useState(false);

  const [approvalModal, setApprovalModal] = useState<{
      user: User;
      type: 'package' | 'category';
      ids: string[];
      displayName: string;
      pkgDuration?: number;
  } | null>(null);
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');

  useEffect(() => {
    refreshData();
  }, []);

  const refreshData = async () => {
    try {
        const initData = await fetchInitialData();
        setCategories(initData.categories);
        setPackages(initData.packages);
        if (initData.settings) setSettings(initData.settings);

        const usersData = await fetchAdminData();
        setUsers(usersData);
    } catch (e) { console.error("Data refresh failed", e); }
  };

  // --- ACTIONS ---

  const initiateApproval = (user: User, type: 'package' | 'category', ids: string[], displayName: string) => {
      let durationDays = 6; 
      if (type === 'package') {
          const pkg = packages.find(p => p.categoryIds.length === ids.length && p.categoryIds.every(id => ids.includes(id)));
          if (pkg && pkg.durationDays) durationDays = pkg.durationDays;
      }

      const now = new Date();
      const end = new Date();
      end.setDate(now.getDate() + durationDays);

      setStartDate(now.toISOString().split('T')[0]);
      setEndDate(end.toISOString().split('T')[0]);

      setApprovalModal({ user, type, ids, displayName, pkgDuration: durationDays });
  };

  const confirmApproval = async () => {
    if (!approvalModal) return;
    const { user, ids } = approvalModal;
    
    // End of day
    const endTs = new Date(endDate).getTime() + (23 * 60 * 60 * 1000);
    
    await approveRequest(user.id, ids, endTs);
    setApprovalModal(null);
    refreshData();
  };

  const toggleSuspend = async (user: User) => {
      await toggleSuspendUser(user.id, !user.isSuspended);
      refreshData();
  };

  const handleFileUpload = async (e: React.ChangeEvent<HTMLInputElement>, catId: string) => {
    if (!e.target.files || !e.target.files[0]) return;
    try {
        setImportStatus(prev => ({ ...prev, [catId]: 'Reading...' }));
        const questions = await parseExcel(e.target.files[0], catId);
        
        setImportStatus(prev => ({ ...prev, [catId]: `Uploading ${questions.length}...` }));
        await importQuestionsToBackend(questions);
        
        setImportStatus(prev => ({ ...prev, [catId]: 'Success!' }));
        refreshData();
    } catch (err: any) {
        setImportStatus(prev => ({ ...prev, [catId]: 'Error!' }));
        alert("Import Error: " + err.message);
    }
  };

  const handleSaveCategory = async () => {
      if (!editingCat) return;
      await saveCategory(editingCat);
      setEditingCat(null);
      refreshData();
  };

  const handleSavePackage = async () => {
      if (!editingPkg) return;
      await savePackage(editingPkg);
      setEditingPkg(null);
      refreshData();
  };

  const toggleCatInPkg = (catId: string) => {
      if (!editingPkg) return;
      const currentIds = editingPkg.categoryIds;
      if (currentIds.includes(catId)) {
          setEditingPkg({ ...editingPkg, categoryIds: currentIds.filter(id => id !== catId) });
      } else {
          setEditingPkg({ ...editingPkg, categoryIds: [...currentIds, catId] });
      }
  };

  const handleSaveSettings = async () => {
      await saveSettings(settings);
      alert("Saved!");
  };

  // --- RENDERS ---

  const renderRequests = () => {
    const requesters = users.filter(u => u.requestedCategories && u.requestedCategories.length > 0);
    
    return (
        <div className="space-y-4">
            <h3 className="text-xl font-bold">{t.requests}</h3>
            {requesters.length === 0 && <p className="text-gray-500 italic">No pending requests.</p>}
            {requesters.map(u => {
                let pendingIds = [...u.requestedCategories];
                const displayItems: Array<{ type: 'package' | 'category', name: string, ids: string[] }> = [];

                packages.forEach(pkg => {
                    if (pkg.categoryIds.length > 0 && pkg.categoryIds.every(cid => pendingIds.includes(cid))) {
                        displayItems.push({
                            type: 'package',
                            name: lang === 'bg' ? pkg.nameBg : pkg.nameEn,
                            ids: pkg.categoryIds
                        });
                        pendingIds = pendingIds.filter(pid => !pkg.categoryIds.includes(pid));
                    }
                });

                pendingIds.forEach(cid => {
                    const cat = categories.find(c => c.id === cid);
                    if (cat) {
                        displayItems.push({
                            type: 'category',
                            name: lang === 'bg' ? cat.nameBg : cat.nameEn,
                            ids: [cid]
                        });
                    }
                });

                return (
                    <div key={u.id} className="bg-white p-4 rounded-lg shadow border-l-4 border-yellow-400">
                        <h4 className="font-bold text-lg mb-2">{u.firstName} {u.lastName} <span className="text-sm font-normal text-gray-500">({u.email})</span></h4>
                        <div className="space-y-2">
                            {displayItems.map((item, idx) => (
                                <div key={idx} className="flex justify-between items-center bg-gray-50 p-3 rounded border border-gray-100">
                                    <div className="flex items-center space-x-2">
                                        {item.type === 'package' ? <PackageIcon size={18} className="text-gold-600"/> : <Tag size={18} className="text-navy-600"/>}
                                        <span className="font-medium">{item.name}</span>
                                        {item.type === 'package' && <span className="text-xs text-gray-500 bg-gray-200 px-2 py-0.5 rounded">Bundle</span>}
                                    </div>
                                    <button 
                                        onClick={() => initiateApproval(u, item.type, item.ids, item.name)}
                                        className="bg-green-600 text-white px-4 py-1.5 rounded text-sm hover:bg-green-700 flex items-center shadow"
                                    >
                                        <Check size={16} className="mr-1"/> {t.approved}
                                    </button>
                                </div>
                            ))}
                        </div>
                    </div>
                );
            })}
        </div>
    );
  };

  const renderUsers = () => (
      <div className="bg-white rounded-lg shadow overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                  <tr>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                      <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                  </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                  {users.filter(u => u.role !== 'ADMIN').map(u => (
                      <tr key={u.id}>
                          <td className="px-6 py-4 whitespace-nowrap">{u.firstName} {u.lastName}</td>
                          <td className="px-6 py-4 whitespace-nowrap">{u.email}</td>
                          <td className="px-6 py-4 whitespace-nowrap text-sm">
                              <button 
                                onClick={() => toggleSuspend(u)}
                                className={`flex items-center space-x-1 ${u.isSuspended ? 'text-green-600' : 'text-red-600'}`}
                              >
                                  {u.isSuspended ? <Unlock size={16}/> : <Lock size={16}/>}
                                  <span>{u.isSuspended ? t.activate : t.suspend}</span>
                              </button>
                          </td>
                      </tr>
                  ))}
              </tbody>
          </table>
      </div>
  );

  const renderCategories = () => (
      <div className="space-y-6">
          <button 
            onClick={() => {
                setEditingCat({ id: `cat-${Date.now()}`, nameBg: '', nameEn: '', price: 5, questionCount: 0, durationMinutes: 60 });
                setIsNewCat(true);
            }}
            className="bg-navy-900 text-white px-4 py-2 rounded flex items-center space-x-2 hover:bg-navy-800"
          >
              <Plus size={18} /> <span>{t.addCategory}</span>
          </button>

          {editingCat && (
              <div className="bg-gray-100 p-4 rounded border border-gray-300 space-y-3">
                  <h4 className="font-bold">{isNewCat ? t.create : t.edit}</h4>
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <input placeholder={t.nameBg} className="p-2 border rounded" value={editingCat.nameBg} onChange={e => setEditingCat({...editingCat, nameBg: e.target.value})} />
                      <input placeholder={t.nameEn} className="p-2 border rounded" value={editingCat.nameEn} onChange={e => setEditingCat({...editingCat, nameEn: e.target.value})} />
                      <input type="number" className="p-2 border rounded" value={editingCat.price} onChange={e => setEditingCat({...editingCat, price: Number(e.target.value)})} />
                      <input type="number" className="p-2 border rounded" value={editingCat.durationMinutes} onChange={e => setEditingCat({...editingCat, durationMinutes: Number(e.target.value)})} />
                  </div>
                  <div className="flex space-x-2">
                      <button onClick={handleSaveCategory} className="bg-green-600 text-white px-4 py-2 rounded">{t.save}</button>
                      <button onClick={() => setEditingCat(null)} className="bg-gray-400 text-white px-4 py-2 rounded">{t.cancel}</button>
                  </div>
              </div>
          )}

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {categories.map(cat => (
                  <div key={cat.id} className="bg-white border p-4 rounded shadow-sm relative group">
                      <div className="flex justify-between items-start mb-2">
                          <h4 className="font-bold text-navy-800 text-sm">{lang === 'bg' ? cat.nameBg : cat.nameEn}</h4>
                          <button onClick={() => { setEditingCat(cat); setIsNewCat(false); }} className="text-blue-500"><Edit2 size={16}/></button>
                      </div>
                      <div className="text-xs text-gray-500 mb-3 space-y-1">
                          <p>{t.price}: {cat.price} €</p>
                          <p>{t.questions}: {cat.questionCount}</p>
                      </div>
                      <label className="flex items-center justify-center space-x-2 text-xs bg-gray-50 hover:bg-gray-100 p-2 rounded cursor-pointer border border-dashed border-gray-300 text-gray-600">
                           <Upload size={14} />
                           <span>{importStatus[cat.id] || t.uploadExcel}</span>
                           <input type="file" className="hidden" accept=".xlsx, .xls" onChange={(e) => handleFileUpload(e, cat.id)} />
                      </label>
                  </div>
              ))}
          </div>
      </div>
  );

  const renderPackages = () => (
      <div className="space-y-6">
           <button 
            onClick={() => {
                setEditingPkg({ id: `pkg-${Date.now()}`, nameBg: '', nameEn: '', price: 50, durationDays: 30, categoryIds: [] });
                setIsNewPkg(true);
            }}
            className="bg-navy-900 text-white px-4 py-2 rounded flex items-center space-x-2 hover:bg-navy-800"
          >
              <Plus size={18} /> <span>{t.addPackage}</span>
          </button>

          {editingPkg && (
              <div className="bg-gray-100 p-4 rounded border border-gray-300 space-y-3">
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      <input placeholder={t.nameBg} className="p-2 border rounded" value={editingPkg.nameBg} onChange={e => setEditingPkg({...editingPkg, nameBg: e.target.value})} />
                      <input placeholder={t.nameEn} className="p-2 border rounded" value={editingPkg.nameEn} onChange={e => setEditingPkg({...editingPkg, nameEn: e.target.value})} />
                      <input type="number" className="p-2 border rounded" value={editingPkg.price} onChange={e => setEditingPkg({...editingPkg, price: Number(e.target.value)})} />
                      <input type="number" className="p-2 border rounded" value={editingPkg.durationDays} onChange={e => setEditingPkg({...editingPkg, durationDays: Number(e.target.value)})} />
                  </div>
                  <div className="bg-white p-3 rounded h-48 overflow-y-auto border">
                      <p className="text-sm font-bold mb-2">Select Categories:</p>
                      {categories.map(cat => (
                          <div key={cat.id} className="flex items-center space-x-2 py-1">
                              <input type="checkbox" checked={editingPkg.categoryIds.includes(cat.id)} onChange={() => toggleCatInPkg(cat.id)} />
                              <span className="text-sm">{lang === 'bg' ? cat.nameBg : cat.nameEn}</span>
                          </div>
                      ))}
                  </div>
                  <div className="flex space-x-2">
                      <button onClick={handleSavePackage} className="bg-green-600 text-white px-4 py-2 rounded">{t.save}</button>
                      <button onClick={() => setEditingPkg(null)} className="bg-gray-400 text-white px-4 py-2 rounded">{t.cancel}</button>
                  </div>
              </div>
          )}
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {packages.map(pkg => (
                  <div key={pkg.id} className="bg-white border p-4 rounded shadow-sm">
                      <div className="flex justify-between">
                          <h4 className="font-bold text-navy-800">{lang === 'bg' ? pkg.nameBg : pkg.nameEn}</h4>
                          <button onClick={() => { setEditingPkg(pkg); setIsNewPkg(false); }} className="text-blue-500"><Edit2 size={16}/></button>
                      </div>
                      <div className="flex justify-between items-center my-1">
                         <p className="text-gold-600 font-bold">{pkg.price} €</p>
                         <p className="text-gray-500 text-xs">Valid: {pkg.durationDays} days</p>
                      </div>
                  </div>
              ))}
          </div>
      </div>
  );

  return (
    <div className="space-y-6 pb-20">
      <h2 className="text-2xl font-bold text-navy-900">{t.adminPanel}</h2>
      <div className="flex overflow-x-auto bg-white rounded shadow-sm">
        <button onClick={() => setActiveTab('reqs')} className="px-4 py-3 border-b-2 font-bold">{t.requests}</button>
        <button onClick={() => setActiveTab('users')} className="px-4 py-3 border-b-2 font-bold">{t.users}</button>
        <button onClick={() => setActiveTab('cats')} className="px-4 py-3 border-b-2 font-bold">{t.categories}</button>
        <button onClick={() => setActiveTab('pkgs')} className="px-4 py-3 border-b-2 font-bold">{t.packages}</button>
        <button onClick={() => setActiveTab('settings')} className="px-4 py-3 border-b-2 font-bold">{t.settings}</button>
      </div>

      <div className="mt-4">
        {activeTab === 'reqs' && renderRequests()}
        {activeTab === 'users' && renderUsers()}
        {activeTab === 'cats' && renderCategories()}
        {activeTab === 'pkgs' && renderPackages()}
        {activeTab === 'settings' && (
            <div className="bg-white p-6 rounded shadow max-w-lg space-y-4">
                <div>
                    <label>{t.revolutLink}</label>
                    <input className="w-full border p-2" value={settings.revolutLink} onChange={e => setSettings({...settings, revolutLink: e.target.value})} />
                </div>
                <div>
                    <label>{t.facebookLink}</label>
                    <input className="w-full border p-2" value={settings.facebookLink} onChange={e => setSettings({...settings, facebookLink: e.target.value})} />
                </div>
                <div>
                    <label>{t.announcement}</label>
                    <textarea className="w-full border p-2" value={settings.announcement} onChange={e => setSettings({...settings, announcement: e.target.value})} />
                </div>
                <button onClick={handleSaveSettings} className="bg-navy-900 text-white px-4 py-2 rounded">{t.save}</button>
            </div>
        )}
      </div>

      {approvalModal && (
          <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
              <div className="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                  <h3 className="text-xl font-bold mb-2">{t.confirmApproval}</h3>
                  <div className="space-y-4 mb-6">
                      <input type="date" className="w-full border rounded p-2" value={startDate} onChange={(e) => setStartDate(e.target.value)} />
                      <input type="date" className="w-full border rounded p-2" value={endDate} onChange={(e) => setEndDate(e.target.value)} />
                  </div>
                  <div className="flex justify-end space-x-3">
                      <button onClick={() => setApprovalModal(null)} className="px-4 py-2 text-gray-600">{t.cancel}</button>
                      <button onClick={confirmApproval} className="px-6 py-2 bg-green-600 text-white font-bold rounded">{t.confirmApproval}</button>
                  </div>
              </div>
          </div>
      )}
    </div>
  );
};

export default AdminPanel;
