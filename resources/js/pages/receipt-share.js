function formatAmount(value) {
    return `Rs. ${Number(value || 0).toLocaleString('en-PK', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function safeFilename(value) {
    return String(value || 'receipt')
        .normalize('NFKD')
        .replace(/[^\w.-]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .toLowerCase() || 'receipt';
}

async function createReceiptPdf(data) {
    const { jsPDF } = await import('jspdf');
    const pdf = new jsPDF({ unit: 'mm', format: 'a4' });
    const pageWidth = pdf.internal.pageSize.getWidth();
    const pageHeight = pdf.internal.pageSize.getHeight();
    const margin = 16;
    const contentWidth = pageWidth - margin * 2;
    let y = 18;

    const ensureSpace = (height) => {
        if (y + height > pageHeight - margin) {
            pdf.addPage();
            y = margin;
        }
    };
    const drawPair = (label, value) => {
        ensureSpace(7);
        pdf.setFont('helvetica', 'normal');
        pdf.text(String(label), margin, y);
        pdf.text(String(value), pageWidth - margin, y, { align: 'right' });
        y += 6;
    };

    pdf.setFont('helvetica', 'bold');
    pdf.setFontSize(18);
    pdf.text(String(data.business || 'Receipt'), pageWidth / 2, y, { align: 'center' });
    y += 7;
    pdf.setFont('helvetica', 'normal');
    pdf.setFontSize(9);
    if (data.address) {
        pdf.text(pdf.splitTextToSize(String(data.address), contentWidth), pageWidth / 2, y, { align: 'center' });
        y += 5;
    }
    if (data.phone) {
        pdf.text(`Phone: ${data.phone}`, pageWidth / 2, y, { align: 'center' });
        y += 5;
    }
    y += 3;
    pdf.setDrawColor(30, 41, 59);
    pdf.setLineWidth(0.6);
    pdf.line(margin, y, pageWidth - margin, y);
    y += 7;

    pdf.setFontSize(10);
    drawPair('Receipt', data.receiptNumber);
    drawPair('Order', data.orderNumber);
    drawPair('Date', data.date);
    drawPair('Payment', data.paymentMethod);
    drawPair('Customer', data.customer);
    y += 2;

    const quantityX = margin + contentWidth * 0.69;
    const totalX = pageWidth - margin;
    pdf.setFont('helvetica', 'bold');
    pdf.text('Item', margin, y);
    pdf.text('Qty', quantityX, y, { align: 'right' });
    pdf.text('Amount', totalX, y, { align: 'right' });
    y += 3;
    pdf.setDrawColor(203, 213, 225);
    pdf.setLineWidth(0.25);
    pdf.line(margin, y, pageWidth - margin, y);
    y += 5;

    pdf.setFont('helvetica', 'normal');
    for (const item of data.items || []) {
        const nameLines = pdf.splitTextToSize(String(item.name || 'Item'), contentWidth * 0.62);
        const detailLines = item.details
            ? pdf.splitTextToSize(String(item.details), contentWidth * 0.62)
            : [];
        const rowHeight = Math.max(6, (nameLines.length + detailLines.length) * 4.5);
        ensureSpace(rowHeight + 2);
        pdf.text(nameLines, margin, y);
        if (detailLines.length) {
            pdf.setFontSize(8);
            pdf.setTextColor(100, 116, 139);
            pdf.text(detailLines, margin, y + nameLines.length * 4.5);
            pdf.setFontSize(10);
            pdf.setTextColor(15, 23, 42);
        }
        pdf.text(String(item.quantity ?? ''), quantityX, y, { align: 'right' });
        pdf.text(formatAmount(item.total), totalX, y, { align: 'right' });
        y += rowHeight + 2;
    }

    y += 2;
    pdf.setDrawColor(203, 213, 225);
    pdf.line(margin, y, pageWidth - margin, y);
    y += 6;
    drawPair('Subtotal', formatAmount(data.subtotal));
    if (Number(data.deliveryFee || 0) > 0) {
        drawPair('Delivery', formatAmount(data.deliveryFee));
    }
    ensureSpace(10);
    pdf.setFont('helvetica', 'bold');
    pdf.setFontSize(13);
    pdf.text('Total', margin, y);
    pdf.text(formatAmount(data.total), totalX, y, { align: 'right' });
    y += 9;
    pdf.setFont('helvetica', 'normal');
    pdf.setFontSize(9);
    if (data.paymentMethod === 'cash') {
        drawPair('Cash received', formatAmount(data.amountReceived));
        drawPair(Number(data.changeAmount || 0) >= 0 ? 'Change' : 'Balance due', formatAmount(Math.abs(Number(data.changeAmount || 0))));
    }
    if (data.notes) {
        ensureSpace(12);
        y += 3;
        pdf.text(pdf.splitTextToSize(`Note: ${data.notes}`, contentWidth), margin, y);
    }

    const blob = pdf.output('blob');
    const filename = `${safeFilename(data.orderNumber || data.receiptNumber)}.pdf`;

    return { blob, filename };
}

document.addEventListener('DOMContentLoaded', () => {
    const shareButton = document.getElementById('share-receipt-pdf');
    const downloadButton = document.getElementById('download-receipt-pdf');
    const status = document.getElementById('receipt-share-status');
    const dataElement = document.getElementById('receipt-pdf-data');
    if (!shareButton || !downloadButton || !status || !dataElement) return;

    let receiptData;
    try {
        receiptData = JSON.parse(dataElement.textContent);
    } catch (error) {
        shareButton.disabled = true;
        downloadButton.disabled = true;
        status.textContent = 'Receipt PDF data could not be loaded. Reload the receipt page and try again.';
        console.error('Unable to read receipt PDF data.', error);
        return;
    }

    const getPdf = () => createReceiptPdf(receiptData);
    const downloadPdf = async () => {
        const { blob, filename } = await getPdf();
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        link.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
        return { blob, filename };
    };

    downloadButton.addEventListener('click', async () => {
        downloadButton.disabled = true;
        status.textContent = 'Preparing receipt PDF…';
        try {
            await downloadPdf();
            status.textContent = 'Receipt PDF downloaded.';
        } catch (error) {
            status.textContent = 'Unable to create the receipt PDF. Please try printing or saving it instead.';
            console.error('Unable to create receipt PDF.', error);
        } finally {
            downloadButton.disabled = false;
        }
    });

    shareButton.addEventListener('click', async () => {
        shareButton.disabled = true;
        status.textContent = 'Preparing receipt PDF…';
        try {
            const { blob, filename } = await getPdf();
            const file = new File([blob], filename, { type: 'application/pdf' });
            if (navigator.canShare?.({ files: [file] }) && navigator.share) {
                await navigator.share({
                    title: `Receipt ${receiptData.orderNumber || ''}`.trim(),
                    text: `Receipt from ${receiptData.business || 'the store'}`,
                    files: [file],
                });
                status.textContent = 'Receipt PDF shared.';
            } else {
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = filename;
                link.click();
                setTimeout(() => URL.revokeObjectURL(url), 1000);
                status.textContent = 'This browser cannot share files directly. The PDF was downloaded; attach it to a message to your customer.';
            }
        } catch (error) {
            if (error.name === 'AbortError') {
                status.textContent = 'Sharing was cancelled.';
            } else {
                status.textContent = 'Unable to share the receipt PDF. Please download it and attach it to a message.';
                console.error('Unable to share receipt PDF.', error);
            }
        } finally {
            shareButton.disabled = false;
        }
    });
});
