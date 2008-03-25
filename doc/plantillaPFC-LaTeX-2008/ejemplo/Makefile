# file: Makefile
# created: 2006-02-11, 18:11:00
# modified: 2006-02-11, 22:00:00
# author: Alvaro Uría Avellanal <fermat at rigel.deusto.es>

FICHERO_TEX=main


all: compile

compile:
	pdflatex ${FICHERO_TEX}.tex
	#makeindex ${FICHERO_TEX}.idx
	bibtex ${FICHERO_TEX}
	bibtex otras
	pdflatex ${FICHERO_TEX}.tex
	pdflatex ${FICHERO_TEX}.tex

view:
	xpdf ${FICHERO_TEX}.pdf

clean:
	ls ${FICHERO_TEX}.* | grep -v \.tex$ | xargs rm
	rm -f *log *lot *lof *aux *toc *pdf *blg *bbl

