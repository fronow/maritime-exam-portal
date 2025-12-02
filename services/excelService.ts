import { Question } from '../types';

export const parseExcel = async (file: File, categoryId: string): Promise<Question[]> => {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();

    reader.onload = (e) => {
      try {
        const data = e.target?.result;
        const XLSX = window.XLSX;
        if (!XLSX) {
            reject("XLSX library not loaded");
            return;
        }
        const workbook = XLSX.read(data, { type: 'binary' });
        const firstSheetName = workbook.SheetNames[0];
        const worksheet = workbook.Sheets[firstSheetName];
        
        // Convert to JSON array of arrays
        const jsonData: any[][] = XLSX.utils.sheet_to_json(worksheet, { header: 1 });
        
        // Column mapping based on user request:
        // Col 1 (index 0): Number (We assume row number/ID)
        // Col 2 (index 1): Question Text
        // Col 3 (index 2): Answer A
        // Col 4 (index 3): Answer B
        // Col 5 (index 4): Answer C
        // Col 6 (index 5): Answer D
        // Col 7 (index 6): Image Filename (optional)
        // Col 8 (index 7): Correct Answer Letter (A, B, C, D) -- Added feature for completeness
        
        const questions: Question[] = [];
        
        // Determine start row. If row 0 col 0 looks like header ("Number", "No"), skip it.
        let startIndex = 0;
        const firstCell = jsonData[0] ? String(jsonData[0][0]).toLowerCase() : '';
        if (firstCell.includes('no') || firstCell.includes('num') || firstCell.includes('номер')) {
            startIndex = 1;
        }

        for (let i = startIndex; i < jsonData.length; i++) {
          const row = jsonData[i];
          if (!row || !row[1]) continue; // Skip empty rows or rows without question text

          // Correct Answer Logic: Check column 8 (index 7). If strictly A, B, C, D use it. Else default 'A'.
          let rawCorrect = row[7] ? String(row[7]).trim().toUpperCase() : 'A';
          if (!['A', 'B', 'C', 'D'].includes(rawCorrect)) {
              rawCorrect = 'A'; 
          }

          questions.push({
            id: `q-${categoryId}-${i}`,
            categoryId,
            originalIndex: i, 
            text: String(row[1]),
            optionA: String(row[2] || ''),
            optionB: String(row[3] || ''),
            optionC: String(row[4] || ''),
            optionD: String(row[5] || ''),
            image: row[6] ? String(row[6]).trim() : undefined,
            correctAnswer: rawCorrect as 'A' | 'B' | 'C' | 'D'
          });
        }
        resolve(questions);

      } catch (err) {
        reject(err);
      }
    };

    reader.onerror = (err) => reject(err);
    reader.readAsBinaryString(file);
  });
};