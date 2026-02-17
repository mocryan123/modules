#!/usr/bin/env python3
"""
Generate PDF from note data using reportlab
"""

import json
import sys
from datetime import datetime

try:
    from reportlab.lib.pagesizes import letter, A4
    from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
    from reportlab.lib.units import inch
    from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, PageBreak
    from reportlab.lib.enums import TA_LEFT, TA_CENTER, TA_JUSTIFY
    from reportlab.lib import colors
except ImportError:
    print("ERROR: reportlab not installed. Install it using: pip install reportlab")
    sys.exit(1)

def create_pdf(data_file):
    """Create PDF from note data"""
    try:
        # Read the JSON data
        with open(data_file, 'r', encoding='utf-8') as f:
            data = json.load(f)
        
        title = data.get('title', 'Untitled Note')
        content = data.get('content', '')
        category = data.get('category', 'General')
        date = data.get('date', '')
        filepath = data.get('filepath', '')
        
        if not filepath:
            print("ERROR: No filepath provided")
            sys.exit(1)
        
        # Create PDF
        doc = SimpleDocTemplate(
            filepath,
            pagesize=letter,
            rightMargin=0.75*inch,
            leftMargin=0.75*inch,
            topMargin=0.75*inch,
            bottomMargin=0.75*inch,
            title=title
        )
        
        # Container for PDF elements
        elements = []
        
        # Define custom styles
        styles = getSampleStyleSheet()
        
        # Title style
        title_style = ParagraphStyle(
            'CustomTitle',
            parent=styles['Heading1'],
            fontSize=24,
            textColor=colors.HexColor('#667eea'),
            spaceAfter=12,
            alignment=TA_LEFT,
            fontName='Helvetica-Bold'
        )
        
        # Category and date style
        meta_style = ParagraphStyle(
            'Meta',
            parent=styles['Normal'],
            fontSize=10,
            textColor=colors.HexColor('#6b7280'),
            spaceAfter=20,
            fontName='Helvetica'
        )
        
        # Content style
        content_style = ParagraphStyle(
            'Content',
            parent=styles['BodyText'],
            fontSize=11,
            alignment=TA_JUSTIFY,
            spaceAfter=12,
            leading=14,
            fontName='Helvetica'
        )
        
        # Add title
        elements.append(Paragraph(title, title_style))
        
        # Add metadata
        meta_text = f"<b>Category:</b> {category} | <b>Date:</b> {date}"
        elements.append(Paragraph(meta_text, meta_style))
        
        # Add separator
        elements.append(Spacer(1, 0.2*inch))
        
        # Add content - split by lines to preserve formatting
        content_lines = content.split('\n')
        for line in content_lines:
            if line.strip():
                elements.append(Paragraph(line.replace('<', '&lt;').replace('>', '&gt;'), content_style))
            else:
                elements.append(Spacer(1, 0.1*inch))
        
        # Add footer
        elements.append(Spacer(1, 0.3*inch))
        footer_style = ParagraphStyle(
            'Footer',
            parent=styles['Normal'],
            fontSize=8,
            textColor=colors.HexColor('#d1d5db'),
            alignment=TA_CENTER,
            fontName='Helvetica'
        )
        elements.append(Paragraph("Generated from BNTM Notepad", footer_style))
        
        # Build PDF
        doc.build(elements)
        print("SUCCESS")
        sys.exit(0)
        
    except FileNotFoundError as e:
        print(f"ERROR: Data file not found: {e}")
        sys.exit(1)
    except json.JSONDecodeError as e:
        print(f"ERROR: Invalid JSON in data file: {e}")
        sys.exit(1)
    except Exception as e:
        print(f"ERROR: {str(e)}")
        sys.exit(1)

if __name__ == '__main__':
    if len(sys.argv) < 2:
        print("ERROR: Data file path required")
        sys.exit(1)
    
    create_pdf(sys.argv[1])
