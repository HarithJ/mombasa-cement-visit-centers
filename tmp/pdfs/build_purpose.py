from pathlib import Path
from reportlab.platypus import SimpleDocTemplate, Paragraph
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.colors import HexColor
from reportlab.lib.pagesizes import A4
out=Path('output/pdf/visit-nyumba-project-purpose-and-overview.pdf')
green=HexColor('#183d33')
styles={
 'title':ParagraphStyle('title',fontName='Helvetica-Bold',fontSize=24,leading=29,textColor=green,spaceAfter=7),
 'sub':ParagraphStyle('sub',fontName='Helvetica',fontSize=12,leading=17,textColor=HexColor('#52665d'),spaceAfter=15),
 'head':ParagraphStyle('head',fontName='Helvetica-Bold',fontSize=12,leading=16,textColor=green,spaceBefore=12,spaceAfter=6,keepWithNext=True),
 'body':ParagraphStyle('body',fontName='Helvetica',fontSize=10,leading=14,textColor=HexColor('#27332e'),spaceAfter=6),
 'bullet':ParagraphStyle('bullet',fontName='Helvetica',fontSize=10,leading=14,textColor=HexColor('#27332e'),leftIndent=10,firstLineIndent=-10,spaceAfter=5)}
story=[]
def p(t,s='body'):story.append(Paragraph(t,styles[s]))
p('Visit Nyumba','title')
p('What we are building and why','sub')
p('Our purpose','head')
p('We are building Visit Nyumba to make it easier for people to discover Nyumba’s destinations, arrange a visit and connect with its community initiatives. The platform gives visitors a clear way to submit their plans and gives the team a central record for coordinating visits and following up.')
p('What we are building','head')
p('A public website and protected staff administration area, centred on visits to Sahajanand Special School, Galana Farm and Kibarani Feeding Center.')
for t in [
 '<b>Destination information:</b> Photographs, location details and directions help visitors understand each destination and plan their trip.',
 '<b>Visit registration:</b> Visitors submit contact details, destination, date, time and group size, then receive a booking reference and confirmation.',
 '<b>Additional arrangements:</b> Visitors can request transport and, for Galana, an overnight stay with arrival and departure dates and guest numbers.',
 '<b>Communication and coordination:</b> Email and SMS notifications share booking details with visitors and nominated staff. Authorised administrators can review bookings in one place.',
 '<b>Post-visit feedback:</b> Private feedback forms linked to bookings help the team understand attendance, visitor experience and areas for improvement.'
]:p('- '+t,'bullet')
p('Why we are building it','head')
p('The aim is to make visit arrangements clearer and easier to manage. A standard form captures the details the team needs, while a shared record supports preparation for visitor numbers, timing, transport and accommodation requests. Confirmations give visitors a reference and a clear summary of what they submitted.')
p('The platform is intended to reduce repeated information gathering and manual follow-up, improve visibility for the people coordinating visits, and make feedback easier to review. These are intended benefits; their impact should be assessed through actual use.')
p('Community-support extensions','head')
p('The project also includes a way to present approved school-support projects, receive school construction requests for staff consideration, and support optional M-Pesa donations. These extensions aim to make community needs easier to communicate and support. Public launch depends on approved content, designated staff recipients and verified payment configuration.')
def footer(c,d):
 c.setStrokeColor(HexColor('#d8e0db'));c.line(46,42,A4[0]-46,42)
 c.setFont('Helvetica',8);c.setFillColor(HexColor('#64746b'))
 c.drawString(46,28,'Visit Nyumba | Project Purpose and Overview')
 c.drawRightString(A4[0]-46,28,'6 October 2026')
SimpleDocTemplate(str(out),pagesize=A4,rightMargin=46,leftMargin=46,topMargin=39,bottomMargin=56,title='Visit Nyumba - Project Purpose and Overview',author='Visit Nyumba').build(story,onFirstPage=footer,onLaterPages=footer)
print(out.resolve())
